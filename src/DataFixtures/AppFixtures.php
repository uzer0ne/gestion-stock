<?php

namespace App\DataFixtures;

use App\Entity\User;
use App\Entity\Categorie;
use App\Entity\Entrepot;
use App\Entity\Fournisseur;
use App\Entity\Produit;
use App\Entity\Stock;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    private UserPasswordHasherInterface $hasher;

    // On injecte le service pour crypter les mots de passe
    public function __construct(UserPasswordHasherInterface $hasher)
    {
        $this->hasher = $hasher;
    }

    public function load(ObjectManager $manager): void
    {
        // 1. Création du Magasinier
        $magasinier = new User();
        $magasinier->setEmail('magasinier@stock.fr');
        $magasinier->setRoles(['ROLE_MAGASINIER']);
        // On crypte le mot de passe "azerty"
        $magasinier->setPassword($this->hasher->hashPassword($magasinier, 'azerty'));
        $manager->persist($magasinier);

        // 2. Création de l'Admin
        $admin = new User();
        $admin->setEmail('admin@stock.fr');
        $admin->setRoles(['ROLE_ADMIN']);
        // On crypte le mot de passe "admin"
        $admin->setPassword($this->hasher->hashPassword($admin, 'admin'));
        $manager->persist($admin);
        // 1. Initialisation de Faker (en français)
        $faker = Factory::create('fr_FR');

        // --- ETAPE 1 : Créer des CATEGORIES ---
        $categories = [];
        $nomsCategories = ['Informatique', 'Téléphonie', 'Électroménager', 'Jeux Vidéo', 'Accessoires'];
        
        foreach ($nomsCategories as $nom) {
            $categorie = new Categorie();
            $categorie->setNom($nom);
            $manager->persist($categorie);
            $categories[] = $categorie; // On garde en mémoire pour donner aux produits plus tard
        }

        // --- ETAPE 2 : Créer des FOURNISSEURS ---
        $fournisseurs = [];
        for ($i = 0; $i < 5; $i++) {
            $fournisseur = new Fournisseur();
            $fournisseur->setNom($faker->company());
            $fournisseur->setEmail($faker->email());
            $fournisseur->setTelephone($faker->phoneNumber());
            $manager->persist($fournisseur);
            $fournisseurs[] = $fournisseur;
        }

        // --- ETAPE 3 : Créer des ENTREPOTS ---
        $entrepots = [];
        $villes = ['Paris Nord', 'Lyon Sud', 'Marseille Port'];
        
        foreach ($villes as $ville) {
            $entrepot = new Entrepot();
            $entrepot->setNom('Entrepôt ' . $ville);
            $entrepot->setVille($ville); // Assure-toi d'avoir ce champ dans ton entité
            $manager->persist($entrepot);
            $entrepots[] = $entrepot;
        }

        // --- ETAPE 4 : Créer des PRODUITS ---
        $produits = [];
        
        // Liste de vrais noms crédibles
        $vraisNoms = [
            'Ordinateur Portable Dell XPS',
            'Souris Logitech MX Master',
            'Clavier Mécanique Corsair',
            'Écran Samsung 27 pouces',
            'Casque Sony Réduction de Bruit',
            'Disque Dur Externe SSD 1To',
            'Clé USB SanDisk 128Go',
            'Câble HDMI tressé 2m',
            'Imprimante HP LaserJet',
            'Enceinte Bluetooth JBL',
            'Smartphone Samsung Galaxy',
            'Manette Xbox Sans Fil',
            'Batterie Externe Anker',
            'Webcam Logitech HD',
            'Tapis de souris Gamer'
        ];

        for ($i = 0; $i < 30; $i++) {
            $produit = new Produit();
            
            // On pioche un nom au hasard dans notre liste et on y ajoute une couleur pour varier
            $nomChoisi = $faker->randomElement($vraisNoms) . ' ' . ucfirst($faker->colorName());
            
            $produit->setNom($nomChoisi); 
            $produit->setReference('REF-' . $faker->unique()->numberBetween(1000, 9999));
            $produit->setPrixVente($faker->randomFloat(2, 10, 500)); // Prix entre 10 et 500
            $produit->setSeuilAlerte($faker->numberBetween(5, 20));
            
            // On assigne une catégorie et un fournisseur au hasard parmi ceux créés au-dessus
            $produit->setCategorie($faker->randomElement($categories));
            $produit->setFournisseur($faker->randomElement($fournisseurs));
            
            // Pour l'image, on ne met rien ou un nom générique si tu as une image par défaut
            // $produit->setImageName('default.jpg'); 

            $manager->persist($produit);
            $produits[] = $produit;
        }

        // --- ETAPE 5 : Créer du STOCK initial ---
        foreach ($produits as $produit) {
            // Pour chaque produit, on crée du stock dans un entrepôt au hasard
            $stock = new Stock();
            $stock->setProduit($produit);
            $stock->setEntrepot($faker->randomElement($entrepots));
            $stock->setQuantite($faker->numberBetween(0, 100));
            $stock->setEmplacement('Allée ' . $faker->randomLetter() . ' - ' . $faker->numberBetween(1, 20));
            
            $manager->persist($stock);
        }

        // On envoie tout en base de données en une seule fois
        $manager->flush();
    }

}