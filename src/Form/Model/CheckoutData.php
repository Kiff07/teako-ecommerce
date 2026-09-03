<?php

declare(strict_types=1);

namespace App\Form\Model;

use Symfony\Component\Validator\Constraints as Assert;

class CheckoutData
{
    #[Assert\NotBlank(message: 'Votre nom est requis.')]
    #[Assert\Length(max: 120)]
    public string $name = '';

    #[Assert\NotBlank(message: 'Votre e-mail est requis.')]
    #[Assert\Email(message: 'Adresse e-mail invalide.')]
    #[Assert\Length(max: 180)]
    public string $email = '';

    #[Assert\Length(max: 20)]
    public ?string $phone = null;

    #[Assert\Length(max: 250)]
    public ?string $deliveryAddress = null;

    #[Assert\Choice(choices: ['delivery', 'pickup'], message: 'Mode invalide.')]
    public string $deliveryMode = 'pickup';

    #[Assert\Length(max: 2500)]
    public ?string $note = null;

    #[Assert\IsTrue(message: 'Vous devez accepter les conditions de commande.')]
    public bool $accept = true;
}
