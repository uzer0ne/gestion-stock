<?php

namespace App\Form;

use App\Entity\Categorie;
use App\Entity\Produit;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Validator\Constraints\File;

class ProduitType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom')
            ->add('reference')
            ->add('prixVente')
            ->add('seuilAlerte', null, [
                'label' => 'Alerter si stock inférieur à :',
                'attr' => ['min' => 0]
            ])
            // C'est ici qu'on corrige le menu déroulant CATEGORIE
            ->add('categorie', EntityType::class, [
                'class' => Categorie::class,
                'choice_label' => 'nom',
                'placeholder' => 'Choisir une catégorie',
            ])
            ->add('fournisseur', null, [
                'choice_label' => 'nom',
                'placeholder' => 'Choisir un fournisseur',
            ])

            ->add('imageFile', FileType::class, [
                'label' => 'Image du produit (JPG/PNG)',
                'mapped' => false, // Important ! Ce champ n'existe pas dans l'entité
                'required' => false,
                'constraints' => [
                    new File([
                        'maxSize' => '2M', // Taille max 2 Mo
                        'mimeTypes' => [
                            'image/jpeg',
                            'image/png',
                            'image/webp',
                        ],
                        'mimeTypesMessage' => 'Merci d\'envoyer une image valide (JPG, PNG, WEBP)',
                    ])
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Produit::class,
        ]);
    }
}
