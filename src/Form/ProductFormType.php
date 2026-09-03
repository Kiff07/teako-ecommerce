<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Category;
use App\Entity\Product;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ProductFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, ['label' => 'Nom du produit'])
            ->add('tag', TextType::class, [
                'label' => 'Badge (optionnel)',
                'required' => false,
                'attr' => ['placeholder' => 'Ex. : Nouveau, Best-seller'],
            ])
            ->add('category', EntityType::class, [
                'class' => Category::class,
                'choice_label' => 'name',
                'label' => 'Catégorie',
                'placeholder' => '— Sans catégorie —',
                'required' => false,
            ])
            ->add('price', NumberType::class, [
                'label' => 'Prix (Ariary)',
                'attr' => ['min' => 0, 'step' => '500'],
            ])
            ->add('stock', NumberType::class, [
                'label' => 'Stock disponible',
                'attr' => ['min' => 0],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'attr' => ['rows' => 4],
            ])
            ->add('recipe', TextareaType::class, [
                'label' => 'Recette / composition (optionnel)',
                'required' => false,
                'attr' => ['rows' => 3],
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'Visible dans la boutique',
                'required' => false,
            ])
            ->add('imagesJson', HiddenType::class, [
                'mapped' => false,
                'label' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Product::class,
        ]);
    }
}
