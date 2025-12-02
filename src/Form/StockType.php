<?php

namespace App\Form;

use App\Entity\Entrepot;
use App\Entity\Produit;
use App\Entity\Stock;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class StockType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('quantite', null, [
                'label' => 'Quantité initiale',
                'attr' => [
                    'min' => 0,       // Bloque la saisie en dessous de 0
                    'placeholder' => 'ex: 10'
                ]
            ])
            ->add('emplacement')
            // Configuration du menu déroulant PRODUIT
            ->add('produit', EntityType::class, [
                'class' => Produit::class,
                'choice_label' => 'nom', // Affiche le nom du produit dans la liste
                'placeholder' => 'Choisir un produit',
            ])
            // Configuration du menu déroulant ENTREPOT
            ->add('entrepot', EntityType::class, [
                'class' => Entrepot::class,
                'choice_label' => 'nom',
                'placeholder' => 'Choisir un entrepôt',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Stock::class,
        ]);
    }
}
