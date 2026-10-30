<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CardBundle\Export;

// One headless Chrome held open and driven through its DevTools protocol over a pipe - no port, no WebSocket client, no Composer dependency: messages are JSON separated by a NUL byte, written to its fd 3 and read from its fd 4. What lets a video be shot frame by frame without launching Chrome for each one (see CardExporter)
class ChromeSession
{
    // Past this silence Chrome is taken for stuck rather than busy
    private const int TIMEOUT = 60;

    /** @var resource */
    private $process;

    /** @var array<int, resource> */
    private array $pipes = [];

    private int $lastId = 0;

    private string $buffer = '';

    // The DevTools session of the page open, null between two pages
    private ?string $session = null;

    private ?string $target = null;

    public function __construct(string $browser, string $profileDir)
    {
        $process = proc_open(
            [$browser, '--headless', '--no-sandbox', '--disable-gpu', '--hide-scrollbars', '--force-color-profile=srgb', '--remote-debugging-pipe', '--user-data-dir=' . $profileDir, 'about:blank'],
            [0 => ['pipe', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w'], 3 => ['pipe', 'r'], 4 => ['pipe', 'w']],
            $this->pipes
        );
        if (!\is_resource($process)) {
            throw new \RuntimeException(\sprintf('"%s" could not be launched.', $browser));
        }
        $this->process = $process;
    }

    // A page opened at the given size, once loaded with its fonts and images
    public function open(string $url, int $width, int $height): void
    {
        $this->closePage();
        $this->target = (string) $this->send('Target.createTarget', ['url' => 'about:blank'])['targetId'];
        $this->session = (string) $this->send('Target.attachToTarget', ['targetId' => $this->target, 'flatten' => true])['sessionId'];

        $this->send('Emulation.setDeviceMetricsOverride', ['width' => $width, 'height' => $height, 'deviceScaleFactor' => 1, 'mobile' => false]);
        $this->send('Page.enable');
        $this->send('Page.navigate', ['url' => $url]);

        // The page's own load read from the document, which no event of another page can be taken for; then the web fonts and the decoded images it does not wait for, the first frame showing neither otherwise
        $this->evaluate('new Promise((resolve) => "complete" === document.readyState ? resolve() : addEventListener("load", resolve, { once: true })).then(() => Promise.all([document.fonts.ready, ...[...document.images].map((image) => image.decode().catch(() => null))]))');
    }

    // A script run in the page, a promise it returns awaited
    public function evaluate(string $expression): mixed
    {
        $result = $this->send('Runtime.evaluate', ['expression' => $expression, 'awaitPromise' => true, 'returnByValue' => true]);
        if (isset($result['exceptionDetails'])) {
            throw new \RuntimeException(\sprintf('The page\'s script failed: %s', json_encode($result['exceptionDetails'], \JSON_UNESCAPED_UNICODE)));
        }

        return $result['result']['value'] ?? null;
    }

    // The page as it stands, written as a JPG
    public function screenshot(string $path, int $quality = 92): void
    {
        $result = $this->send('Page.captureScreenshot', ['format' => 'jpeg', 'quality' => $quality, 'optimizeForSpeed' => true]);
        if (false === file_put_contents($path, base64_decode((string) $result['data'], true))) {
            throw new \RuntimeException(\sprintf('"%s" could not be written.', $path));
        }
    }

    // Chrome closed, whatever state it is in - and never throwing, called as it is from a finally whose own error has to come through
    public function close(): void
    {
        if (!\is_resource($this->process)) {
            return;
        }
        try {
            $this->closePage();
            $this->send('Browser.close', page: false);
        } catch (\Throwable) {
            // Already gone
        }
        foreach ($this->pipes as $pipe) {
            @fclose($pipe);
        }

        // A Chrome deaf to Browser.close would keep proc_close() waiting
        proc_terminate($this->process);
        proc_close($this->process);
    }

    // The page open closed, the next one opening in a fresh target
    private function closePage(): void
    {
        if (null !== $this->target) {
            $this->send('Target.closeTarget', ['targetId' => $this->target], false);
        }
        $this->target = null;
        $this->session = null;
    }

    // One command sent and its answer waited for, to the page open unless told otherwise
    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    private function send(string $method, array $params = [], bool $page = true): array
    {
        $message = ['id' => ++$this->lastId, 'method' => $method, 'params' => (object) $params];
        if ($page && null !== $this->session) {
            $message['sessionId'] = $this->session;
        }
        // A dead Chrome's pipe raises a notice the error handler would turn into an exception of its own
        if (false === @fwrite($this->pipes[3], json_encode($message, \JSON_THROW_ON_ERROR) . "\0")) {
            throw new \RuntimeException('Chrome stopped midway.');
        }

        do {
            $answer = $this->read();
        } while (($answer['id'] ?? null) !== $this->lastId);

        if (isset($answer['error'])) {
            throw new \RuntimeException(\sprintf('Chrome refused %s: %s', $method, json_encode($answer['error'], \JSON_UNESCAPED_UNICODE)));
        }

        return $answer['result'] ?? [];
    }

    // The next message Chrome writes, answer or event
    /** @return array<string, mixed> */
    private function read(): array
    {
        while (false === $end = strpos($this->buffer, "\0")) {
            $read = [$this->pipes[4]];
            $write = $except = null;
            if (1 > (int) stream_select($read, $write, $except, self::TIMEOUT)) {
                throw new \RuntimeException(\sprintf('Chrome has not answered for %d seconds.', self::TIMEOUT));
            }
            $chunk = fread($this->pipes[4], 1 << 20);
            if (false === $chunk || ('' === $chunk && feof($this->pipes[4]))) {
                throw new \RuntimeException('Chrome stopped midway.');
            }
            $this->buffer .= $chunk;
        }

        $message = json_decode(substr($this->buffer, 0, $end), true, flags: \JSON_THROW_ON_ERROR);
        $this->buffer = substr($this->buffer, $end + 1);

        return \is_array($message) ? $message : [];
    }
}
