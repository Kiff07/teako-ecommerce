<?php

declare(strict_types=1);

namespace App\Form;

use App\Form\Model\CheckoutData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CheckoutType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom complet',
                'attr' => ['placeholder' => 'Ex. : Hery Rakoto', 'autocomplete' => 'name'],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Adresse e-mail',
                'attr' => ['placeholder' => 'vous@exemple.mg', 'autocomplete' => 'email'],
            ])
            ->add('phone', TelType::class, [
                'label' => 'Téléphone (optionnel)',
                'required' => false,
                'attr' => ['placeholder' => '+261 34 00 000 00', 'autocomplete' => 'tel'],
            ])
            ->add('deliveryMode', ChoiceType::class, [
                'label' => 'Réception',
                'expanded' => true,
                'choices' => [
                    'À retirer sur place' => 'pickup',
                    'Livraison Antananarivo' => 'delivery',
                ],
                'data' => 'pickup',
                'choice_attr' => fn ($choice, $key, $value) => ['data-mode' => $value],
            ])
            ->add('deliveryAddress', TextareaType::class, [
                'label' => 'Adresse de livraison',
                'required' => false,
                'attr' => [
                    'rows' => 2,
                    'placeholder' => 'Lot, rue, quartier — Antananarivo',
                    'data-delivery' => 'true',
                ],
            ])
            ->add('note', TextareaType::class, [
                'label' => 'Remarque pour la préparation (optionnel)',
                'required' => false,
                'attr' => ['rows' => 2, 'placeholder' => 'Moins sucré, sans glaçons, etc.'],
            ])
            ->add('accept', CheckboxType::class, [
                'label' => 'Je confirme ma commande et son paiement à la réception.',
                'data' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CheckoutData::class,
            'csrf_token_id' => 'checkout',
            'attr' => ['novalidate' => 'novalidate'],
        ]);
    }
}
