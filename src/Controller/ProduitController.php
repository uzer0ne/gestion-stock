<?php

namespace App\Controller;

use App\Entity\Produit;
use App\Form\ProduitType;
use App\Repository\ProduitRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/produit')]
final class ProduitController extends AbstractController
{
    #[Route(name: 'app_produit_index', methods: ['GET'])]
    public function index(ProduitRepository $produitRepository): Response
    {
        return $this->render('produit/index.html.twig', [
            'produits' => $produitRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_produit_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_MAGASINIER')] // Seul le magasinier/admin peut créer un produit
    public function new(Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        $produit = new Produit();
        $form = $this->createForm(ProduitType::class, $produit);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            // --- DEBUT GESTION IMAGE ---
            $imageFile = $form->get('imageFile')->getData();

            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$imageFile->guessExtension();

                try {
                    $imageFile->move(
                        $this->getParameter('produits_directory'),
                        $newFilename
                    );
                } catch (FileException $e) {
                    // Gérer l'erreur si besoin
                }

                $produit->setImageName($newFilename);
            }
            // --- FIN GESTION IMAGE ---
            
            $entityManager->persist($produit);
            $entityManager->flush();
            
            $this->addFlash('success', 'Nouveau produit créé avec succès.');
            return $this->redirectToRoute('app_produit_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('produit/new.html.twig', [
            'produit' => $produit,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_produit_show', methods: ['GET'])]
    public function show(Produit $produit): Response
    {
        return $this->render('produit/show.html.twig', [
            'produit' => $produit,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_produit_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_MAGASINIER')] // Seul le magasinier/admin peut modifier
    public function edit(Request $request, Produit $produit, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        $form = $this->createForm(ProduitType::class, $produit);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // --- DEBUT GESTION IMAGE ---
            $imageFile = $form->get('imageFile')->getData();

            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$imageFile->guessExtension();

                try {
                    $imageFile->move(
                        $this->getParameter('produits_directory'),
                        $newFilename
                    );
                } catch (FileException $e) {
                    // Gérer l'erreur si besoin
                }

                $produit->setImageName($newFilename);
            }
            // --- FIN GESTION IMAGE ---
            
            $entityManager->flush();

            $this->addFlash('success', 'Produit mis à jour avec succès.');
            return $this->redirectToRoute('app_produit_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('produit/edit.html.twig', [
            'produit' => $produit,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_produit_delete', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')] // SEUL l'administrateur peut supprimer un produit
    public function delete(Request $request, Produit $produit, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$produit->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($produit);
            $entityManager->flush();
            $this->addFlash('success', 'Produit supprimé avec succès.');
        }

        return $this->redirectToRoute('app_produit_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/recherche/ajax', name: 'app_produit_recherche_ajax', methods: ['GET'])]
    public function rechercheAjax(Request $request, ProduitRepository $produitRepository): JsonResponse
    {
        $term = $request->query->get('term'); 

        if (!$term) {
            return new JsonResponse([]);
        }

        $produits = $produitRepository->createQueryBuilder('p')
            ->where('p.nom LIKE :term')
            ->orWhere('p.reference LIKE :term')
            ->setParameter('term', '%' . $term . '%')
            ->getQuery()
            ->getResult();

        $results = [];
        foreach ($produits as $p) {
            $results[] = [
                'id' => $p->getId(),
                'nom' => $p->getNom(),
                'reference' => $p->getReference(),
                'image' => $p->getImageName() ? '/uploads/products/' . $p->getImageName() : null,
                'url_show' => $this->generateUrl('app_produit_show', ['id' => $p->getId()])
            ];
        }

        return new JsonResponse($results);
    }

    #[Route('/{id}/clone', name: 'app_produit_clone', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_MAGASINIER')] // Le magasinier peut cloner des produits
    public function clone(
        Produit $produitOriginal, 
        Request $request, 
        EntityManagerInterface $entityManager,
        ProduitRepository $produitRepository // <-- J'ai ajouté l'injection du Repository ici
    ): Response
    {
        $nouveauProduit = clone $produitOriginal;

        // 1. Nettoyage du nom pour éviter les "Nom (Copie) (Copie)"
        $nomDeBase = str_replace(' (Copie)', '', $produitOriginal->getNom());
        $nouveauProduit->setNom($nomDeBase . ' (Copie)');

        // 2. ALGORITHME DE RÉFÉRENCE INTELLIGENTE
        $baseReference = $produitOriginal->getReference();
        $counter = 1;
        $nouvelleReference = $baseReference . '-' . $counter;

        // Tant que la référence existe déjà en BDD, on augmente le chiffre
        while ($produitRepository->findOneBy(['reference' => $nouvelleReference])) {
            $counter++;
            $nouvelleReference = $baseReference . '-' . $counter;
        }

        // On assigne la référence libre trouvée
        $nouveauProduit->setReference($nouvelleReference);

        $form = $this->createForm(ProduitType::class, $nouveauProduit);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($nouveauProduit);
            $entityManager->flush();

            $this->addFlash('success', 'Le produit a été dupliqué avec succès avec la référence : ' . $nouvelleReference);

            return $this->redirectToRoute('app_produit_index');
        }

        return $this->render('produit/new.html.twig', [
            'produit' => $nouveauProduit,
            'form' => $form,
            'is_clone' => true 
        ]);
    }
}