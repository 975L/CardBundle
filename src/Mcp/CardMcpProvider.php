<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CardBundle\Mcp;

use c975L\CardBundle\Entity\CardBack;
use c975L\CardBundle\Entity\CardSkin;
use c975L\CardBundle\Repository\CardBackRepository;
use c975L\CardBundle\Repository\CardSkinRepository;
use c975L\CardBundle\Service\BackStyles;
use c975L\ConfigBundle\Mcp\McpTool;
use c975L\ConfigBundle\Mcp\McpToolProviderInterface;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Mcp\Server\Exception\ToolCallException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\String\Slugger\AsciiSlugger;

// The card backs and skins on the site's MCP endpoint, for an account that edits them on the back office: listed, read, an unpublished back added and its name and style written, publishing, pricing and pictures left to a human
class CardMcpProvider implements McpToolProviderInterface
{
    // Backs a list gives at most
    public const int LIMIT = 50;

    public function __construct(
        private readonly CardBackRepository $cardBackRepository,
        private readonly CardSkinRepository $cardSkinRepository,
        private readonly BackStyles $backStyles,
        private readonly EntityManagerInterface $entityManager,
        private readonly ConfigServiceInterface $configService,
        private readonly Security $security,
    ) {
    }

    public function getMcpKey(): string
    {
        return 'card';
    }

    public function getMcpInstructions(): string
    {
        return 'Card backs are held once for every pack, skins pack by pack; the cards themselves are the site\'s own. A back added by an agent is unpublished, without price nor picture: a human adds its pictures, prices and publishes it from the back office.';
    }

    public function getMcpTools(): iterable
    {
        yield new McpTool('card_back_list', 'Card backs', 'The card backs whose name holds the search words, every back without them, unpublished ones included, by name.', $this->listBacks(...));
        yield new McpTool('card_back_get', 'Card back', 'A card back: name, slug, tone, effect, price in cents, published, standard, the packs it has a picture for, and the tones and effects the site offers.', $this->getBack(...));
        yield new McpTool('card_back_create', 'Add an unpublished card back', 'Adds a card back, unpublished and not for sale, with its name and an optional tone and effect among those the site offers.', $this->createBack(...), true);
        yield new McpTool('card_back_edit', 'Write a card back', 'Changes only the name, tone or effect given of a card back, its slug staying as it is.', $this->editBack(...), true);
        yield new McpTool('card_skin_list', 'Card skins', 'The skins of the card faces whose name holds the search words, every skin without them, unpublished ones included, with the packs each dresses.', $this->listSkins(...));
    }

    // The backs whose name holds the words, every one without them
    /**
     * @param ?string $search words looked for in the names
     *
     * @return list<array{id: ?int, name: ?string, slug: ?string, published: bool, standard: bool}>
     */
    public function listBacks(?string $search = null): array
    {
        $this->guard();

        return array_map($this->backSummary(...), $this->search($this->cardBackRepository->createQueryBuilder('r')->setMaxResults(self::LIMIT), $search));
    }

    // A back with its style, its price and the packs its pictures are for
    /**
     * @param int $back id of the card back
     *
     * @return array{id: ?int, name: ?string, slug: ?string, published: bool, standard: bool, tone: string, effect: string, price: ?int, packs: list<string>, tones: list<string>, effects: list<string>}
     */
    public function getBack(int $back): array
    {
        $found = $this->foundBack($back);
        $packs = [];
        foreach ($found->getPictures() as $picture) {
            $packs[] = (string) $picture->getPack();
        }

        return [
            ...$this->backSummary($found),
            'tone' => $found->getTone(),
            'effect' => $found->getEffect(),
            'price' => $found->getPrice(),
            'packs' => array_values(array_unique($packs)),
            'tones' => array_keys($this->backStyles->tones()),
            'effects' => array_keys($this->backStyles->effects()),
        ];
    }

    // An unpublished back added, neither standard nor priced, its slug made from its name and kept unique by a number
    /**
     * @param string  $name   name of the back, the same for every pack
     * @param ?string $tone   its tone, among those card_back_get lists
     * @param ?string $effect its effect, among those card_back_get lists
     *
     * @return array{id: ?int, slug: ?string}
     */
    public function createBack(string $name, ?string $tone = null, ?string $effect = null): array
    {
        $this->guard();
        $name = mb_substr(trim($name), 0, 60);
        if ('' === $name) {
            throw new ToolCallException('The name is empty.');
        }
        $back = new CardBack()
            ->setName($name)
            ->setSlug($this->slug($name))
            ->setTone($this->style($tone, $this->backStyles->tones(), 'tone') ?? CardBack::NO_STYLE)
            ->setEffect($this->style($effect, $this->backStyles->effects(), 'effect') ?? CardBack::NO_STYLE);
        $this->entityManager->persist($back);
        $this->entityManager->flush();

        return ['id' => $back->getId(), 'slug' => $back->getSlug()];
    }

    // A back's name, tone or effect changed, only those given, the slug locked as on the back office
    /**
     * @param int     $back   id of the card back
     * @param ?string $name   new name
     * @param ?string $tone   new tone, among those card_back_get lists
     * @param ?string $effect new effect, among those card_back_get lists
     *
     * @return array{id: ?int}
     */
    public function editBack(int $back, ?string $name = null, ?string $tone = null, ?string $effect = null): array
    {
        $found = $this->foundBack($back);
        if ('' !== trim((string) $name)) {
            $found->setName(mb_substr(trim((string) $name), 0, 60));
        }
        $found->setTone($this->style($tone, $this->backStyles->tones(), 'tone') ?? $found->getTone());
        $found->setEffect($this->style($effect, $this->backStyles->effects(), 'effect') ?? $found->getEffect());
        $this->entityManager->flush();

        return ['id' => $found->getId()];
    }

    // The skins whose name holds the words, every one without them, with the packs each dresses read in the same query, a site having few
    /**
     * @param ?string $search words looked for in the names
     *
     * @return list<array{id: ?int, name: ?string, slug: ?string, published: bool, price: ?int, packs: list<string>}>
     */
    public function listSkins(?string $search = null): array
    {
        $this->guard();

        return array_map(static fn (CardSkin $skin): array => [
            'id' => $skin->getId(),
            'name' => $skin->getName(),
            'slug' => $skin->getSlug(),
            'published' => $skin->isPublished(),
            'price' => $skin->getPrice(),
            'packs' => $skin->getPacks(),
        ], $this->search($this->cardSkinRepository->createQueryBuilder('r')->addSelect('p')->leftJoin('r.pictures', 'p'), $search));
    }

    // The backs and skins are the editor's business, as on the back office
    private function guard(): void
    {
        if (!$this->security->isGranted((string) $this->configService->get('site-role-editor'))) {
            throw new ToolCallException('This account may not work on the card backs and skins.');
        }
    }

    // A back by its id
    private function foundBack(int $id): CardBack
    {
        $this->guard();
        $back = $this->cardBackRepository->find($id);
        if (!$back instanceof CardBack) {
            throw new ToolCallException(sprintf('No card back %d.', $id));
        }

        return $back;
    }

    // The rows whose name holds the words, by name
    /** @return list<mixed> */
    private function search(QueryBuilder $builder, ?string $search): array
    {
        $builder->orderBy('r.name');
        if ('' !== trim((string) $search)) {
            $builder->andWhere('r.name LIKE :words')->setParameter('words', '%' . addcslashes(trim((string) $search), '%_') . '%');
        }

        return $builder->getQuery()->getResult();
    }

    // A back in a list
    /** @return array{id: ?int, name: ?string, slug: ?string, published: bool, standard: bool} */
    private function backSummary(CardBack $back): array
    {
        return [
            'id' => $back->getId(),
            'name' => $back->getName(),
            'slug' => $back->getSlug(),
            'published' => $back->isPublished(),
            'standard' => $back->isStandard(),
        ];
    }

    // A tone or an effect the site offers, null when none is given, any other refused
    /** @param array<string, mixed> $offered */
    private function style(?string $value, array $offered, string $kind): ?string
    {
        $value = trim((string) $value);
        if ('' === $value) {
            return null;
        }
        if (!\array_key_exists($value, $offered)) {
            throw new ToolCallException(sprintf('No %s "%s", the site offers: %s.', $kind, $value, implode(', ', array_keys($offered))));
        }

        return $value;
    }

    // A slug from the name, a number added while another back holds it
    private function slug(string $name): string
    {
        $base = trim(mb_substr(strtolower(new AsciiSlugger()->slug($name)->toString()), 0, 70), '-') ?: 'back';
        $slug = $base;
        for ($number = 2; null !== $this->cardBackRepository->findOneBy(['slug' => $slug]); ++$number) {
            $slug = $base . '-' . $number;
        }

        return $slug;
    }
}
