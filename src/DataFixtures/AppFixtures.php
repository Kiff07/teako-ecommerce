<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Category;
use App\Entity\Customer;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\Product;
use App\Entity\ProductImage;
use App\Enum\OrderStatus;
use App\Service\ImageProcessor;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

final class AppFixtures extends Fixture
{
    private string $seedsDir;

    public function __construct(
        private readonly ImageProcessor $processor,
        private readonly string $projectDir,
    ) {
        $this->seedsDir = $projectDir.'/assets/seeds/photos';
    }

    public function load(ObjectManager $em): void
    {
        $categories = [];
        foreach ($this->categories() as $slug => $name) {
            $category = new Category();
            $category->setName($name);
            $category->setDescription($this->categoryDescription($name));
            $em->persist($category);
            $categories[$slug] = $category;
        }
        $em->flush();

        $products = [];
        foreach ($this->products() as $def) {
            /** @var Category $category */
            $category = $categories[$def['category']];

            $product = new Product();
            $product->setCategory($category);
            $product->setName($def['name']);
            $product->setTag($def['tag'] ?? null);
            $product->setPrice($def['price']);
            $product->setDescription($def['desc']);
            $product->setRecipe($def['recipe'] ?? null);
            $product->setStock(0);
            $product->setIsActive(true);

            $em->persist($product);
            $em->flush(); // gives us the id used for the media folder

            foreach ($def['photos'] ?? [] as $photo) {
                $this->attachPhoto($em, $product, $photo);
            }
            $em->flush();

            $products[] = $product;
        }

        $this->seedOrders($em, $products);

        // Final, coherent stock state (the demo orders already decremented stock).
        $final = [
            'iced-caramel-macchiato' => 24,
            'cappuccino-teako' => 18,
            'strawberry-milkshake' => 12,
            'smoothie-berries' => 15,
            'passion-combava-vanille' => 16,
            'cocoa-banana' => 10,
            'iced-coffee-nuts' => 8,
            'matcha-latte-velvet' => 11,
            'fluffy-oreo' => 3,   // stock faible -> alerte admin
            'the-orange-epice' => 5,  // stock faible
            'the-pomme-epice' => 7,
            'matcha-latte-orange' => 4, // stock faible
            'cafe-marmelade' => 9,
            'frappe-cafe' => 0,   // rupture -> bouton désactivé
        ];
        foreach ($products as $product) {
            $slug = $product->getSlug();
            if (isset($final[$slug])) {
                $product->setStock($final[$slug]);
            }
        }
        $em->flush();
    }

    private function attachPhoto(ObjectManager $em, Product $product, string $seedFile): void
    {
        $source = $this->seedsDir.'/'.$seedFile;
        if (!is_file($source)) {
            return;
        }

        $productId = (int) $product->getId();
        $dir = $this->processor->ensureProductDir($productId);
        $target = $dir.'/'.$seedFile;
        if (!is_file($target)) {
            copy($source, $target);
        }

        $url = sprintf('/media/%d/%s', $productId, $seedFile);
        [$w, $h] = array_values($this->processor->dimensionsOf($url));

        $image = new ProductImage();
        $image->setProduct($product);
        $image->setFilename($url);
        $image->setAltText($product->getName());
        $image->setDisplayOrder(count($product->getImages()) + 1);
        $image->setIsMain(0 === count($product->getImages()));
        $image->setWidth($w ?: null);
        $image->setHeight($h ?: null);
        $em->persist($image);

        // Warm the image cache so first page loads are instant
        foreach (['blur', 'thumb', 'card', 'big'] as $variant) {
            $this->processor->variant($url, $variant);
        }
    }

    private function seedOrders(ObjectManager $em, array $products): void
    {
        $statusFlow = [
            OrderStatus::DELIVERED, OrderStatus::DELIVERED, OrderStatus::DELIVERED, OrderStatus::DELIVERED,
            OrderStatus::DELIVERED, OrderStatus::SHIPPED, OrderStatus::PAID, OrderStatus::PENDING,
        ];
        $names = ['Hery Rakoto', 'Miora Andria', 'Nirina Raso', 'Faly Rabe', 'Lova Ranja', 'Tojo Naina', 'Voahangy', 'Kanto'];
        $domains = ['gmail.com', 'yahoo.fr', 'outlook.com', 'moov.mg', 'orange.mg', 'telma.mg'];
        $customers = [];
        $rng = new \Random\Randomizer();

        $today = new \DateTimeImmutable('now');
        for ($i = 0; $i < 26; ++$i) {
            $daysAgo = $i < 3 ? 0 : $rng->getInt(1, 13);
            $created = $today->modify('-'.$daysAgo.' days')->setTime($rng->getInt(9, 20), $rng->getInt(0, 59), $rng->getInt(0, 59));

            $pick = $rng->pickArrayKeys($products, $rng->getInt(1, 3));
            $name = $names[$rng->getInt(0, count($names) - 1)];
            $email = mb_strtolower(str_replace(' ', '.', $name)).'@'.$domains[$rng->getInt(0, count($domains) - 1)];

            $customer = $customers[$email] ?? null;
            if (null === $customer) {
                $customer = new Customer();
                $customer->setEmail($email);
                $customer->setFullName($name);
                $em->persist($customer);
                $customers[$email] = $customer;
            }

            $order = new Order();
            $order->setCustomer($customer);
            $order->setCustomerName($name);
            $order->setCustomerEmail($email);
            $order->setStatus($statusFlow[$i % count($statusFlow)]);

            $total = 0;
            foreach ($pick as $idx) {
                /** @var Product $product */
                $product = $products[$idx];
                $qty = $rng->getInt(1, 3);
                $line = new OrderItem();
                $line->setProduct($product);
                $line->setQuantity($qty);
                $order->addItem($line);
                $total += $line->getLineTotalCents();
            }
            $order->setTotalAmount($total / 100);

            // back-date the order (public property via reflection to keep the API clean)
            $ref = new \ReflectionProperty(Order::class, 'createdAt');
            $ref->setAccessible(true);
            $ref->setValue($order, $created);

            $em->persist($order);
            $em->flush();
        }
    }

    /** @return array<string, string> */
    private function categories(): array
    {
        return [
            'signature' => 'Signature',
            'glaces' => 'Cafés glacés & Crémeux',
            'smoothies' => 'Smoothies & Milkshakes',
            'chauds' => 'Cafés & Lattes chauds',
            'thes' => 'Thés & Matcha',
        ];
    }

    private function categoryDescription(string $name): string
    {
        return match ($name) {
            'Signature' => 'La star de la maison Teako — celle dont on devient accro.',
            'Cafés glacés & Crémeux' => 'Nos incontournables servis bien frais, généreux et gourmands.',
            'Smoothies & Milkshakes' => 'Fruits frais, crème douce et onctuosité à la malgache.',
            'Cafés & Lattes chauds' => 'Le savoir-faire barista de Teako, à savourer chaud.',
            default => 'Thés et matchas sélectionnés avec soin pour les vrais gourmands.',
        };
    }

    /** @return list<array{name: string, category: string, price: int, tag?: string, desc: string, recipe?: string, photos?: list<string>}> */
    private function products(): array
    {
        return [
            [
                'name' => 'Passion Combava Vanille',
                'category' => 'signature',
                'price' => 20000,
                'tag' => 'Signature Teako',
                'desc' => 'Notre création la plus aimée : la passion, le combava et une touche de vanille de Madagascar. « Goûte et deviens accro ! »',
                'recipe' => 'Jus de fruits de la passion frais, sirop de combava, vanille bourbon, glaçons pilés.',
                'photos' => ['u-08.jpg', 'u-09.jpg'],
            ],
            [
                'name' => 'Iced Caramel Macchiato',
                'category' => 'glaces',
                'price' => 20000,
                'tag' => 'Best-seller',
                'desc' => 'L’incontournable de Teako : espresso serré, lait frais et caramel doré versé sur glace. Doux, intense, irrésistible.',
                'recipe' => 'Espresso, lait frais, sauce caramel, glace.',
                'photos' => ['drink-1-caramel-latte.jpg', 'u-04.jpg', 'u-05.jpg'],
            ],
            [
                'name' => 'Iced Coffee Nuts',
                'category' => 'glaces',
                'price' => 20000,
                'desc' => 'Un café glacé rehaussé de notes de noisette torréfiée et de praliné. Pour les amateurs de saveurs profondes.',
                'recipe' => 'Café glacé, lait, sirop noisette, éclats de praliné.',
                'photos' => ['u-11.jpg'],
            ],
            [
                'name' => 'Cocoa Banana',
                'category' => 'glaces',
                'price' => 18000,
                'tag' => 'Nouveau',
                'desc' => 'Chocolat intense, banane bien mûre et lait glacé mixés minute. La gourmandise fruitée qui réconforte.',
                'recipe' => 'Banane, cacao, lait, miel de Madagascar.',
                'photos' => ['u-10.jpg'],
            ],
            [
                'name' => 'Fluffy Oreo',
                'category' => 'glaces',
                'price' => 22000,
                'tag' => 'Coup de cœur',
                'desc' => 'Notre milkshake moelleux aux cookies Oreo, tourbillonné de chantilly et de miettes croustillantes.',
                'recipe' => 'Crème glacée vanille, Oreo, lait, chantilly.',
                'photos' => ['u-03.jpg'],
            ],
            [
                'name' => 'Frappé Café Crémeux',
                'category' => 'glaces',
                'price' => 18000,
                'desc' => 'Café frappé à la crème, onctueux et peu sucré. Disponible en version décaféiné sur demande.',
                'recipe' => 'Espresso, lait, glace pilée, crème.',
                'photos' => [],
            ],
            [
                'name' => 'Strawberry Milkshake Crémeux',
                'category' => 'smoothies',
                'price' => 18000,
                'tag' => 'Nouveau',
                'desc' => 'Fraises fraîches et crème glacée, mixées jusqu’à la perfection. La douceur rose qui fait sourire.',
                'recipe' => 'Fraises, crème glacée, lait, vanille.',
                'photos' => ['drink-2-strawberry-milkshake.jpg', 'u-06.jpg'],
            ],
            [
                'name' => 'Smoothie Fruits Rouges',
                'category' => 'smoothies',
                'price' => 16000,
                'desc' => 'Un mélange velouté de baies rouges et de crème légère, sucré juste ce qu’il faut. Vitaminé et généreux.',
                'recipe' => 'Baies rouges, banane, yaourt, miel.',
                'photos' => ['drink-3-berry-smoothie.jpg', 'u-07.jpg'],
            ],
            [
                'name' => 'Cappuccino Teako',
                'category' => 'chauds',
                'price' => 13000,
                'tag' => 'Nouveau',
                'desc' => 'Le grand classique italien revisité : espresso, mousse de lait soyeuse et chocolat râpé en finition.',
                'recipe' => 'Espresso, lait micro-moussé, chocolat râpé.',
                'photos' => ['drink-0-cappuccino.jpg', 'u-01.jpg'],
            ],
            [
                'name' => 'Café à la Marmelade',
                'category' => 'chauds',
                'price' => 12000,
                'desc' => 'Un café doux et acidulé, parfumé à la marmelade d’orange faite maison. L’équilibre parfait entre amer et sucré.',
                'recipe' => 'Café filtre, marmelade d’orange, zeste.',
                'photos' => [],
            ],
            [
                'name' => 'Matcha Latte Velvet',
                'category' => 'thes',
                'price' => 20000,
                'tag' => 'Best-seller',
                'desc' => 'Matcha cérémonial de qualité, fouetté au lait vapeur. Végétal, crémeux, avec une pointe d’amertume noble.',
                'recipe' => 'Matcha cérémonial, lait, sirop de vanille.',
                'photos' => ['u-02.jpg'],
            ],
            [
                'name' => 'Matcha Latte Orange',
                'category' => 'thes',
                'price' => 20000,
                'desc' => 'L’audace Teako : matcha et orange sanguine, un duo étonnant entre fraîcheur et profondeur.',
                'recipe' => 'Matcha, jus d’orange sanguine, lait.',
                'photos' => [],
            ],
            [
                'name' => 'Thé Orange Épicé',
                'category' => 'thes',
                'price' => 15000,
                'desc' => 'Infusion chaude d’orange et épices douces. Le réconfort des après-midis frais d’Antananarivo.',
                'recipe' => 'Thé noir, écorce d’orange, cannelle, clou de girofle.',
                'photos' => [],
            ],
            [
                'name' => 'Thé Pomme Épicé',
                'category' => 'thes',
                'price' => 15000,
                'desc' => 'Pomme fruitée, cannelle et douceur vanillée : un thé qui embaume et apaise.',
                'recipe' => 'Thé noir, pomme, cannelle, vanille.',
                'photos' => [],
            ],
        ];
    }
}
