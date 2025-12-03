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
                // On nettoie le nom du fichier (supprime les accents, espaces...)
                $safeFilename = $slugger->slug($originalFilename);
                // On ajoute un ID unique pour éviter d'écraser une image existante
                $newFilename = $safeFilename.'-'.uniqid().'.'.$imageFile->guessExtension();

                try {
                    // On déplace le fichier dans le dossier configuré
                    $imageFile->move(
                        $this->getParameter('produits_directory'),
                        $newFilename
                    );
                } catch (FileException $e) {
                    // Gérer l'erreur si besoin
                }

                // On enregistre seulement le NOM du fichier dans la base
                $produit->setImageName($newFilename);
            }
            // --- FIN GESTION IMAGE ---
            $entityManager->persist($produit);
            $entityManager->flush();

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
    public function edit(Request $request, Produit $produit, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        $form = $this->createForm(ProduitType::class, $produit);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // --- DEBUT GESTION IMAGE ---
            $imageFile = $form->get('imageFile')->getData();

            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                // On nettoie le nom du fichier (supprime les accents, espaces...)
                $safeFilename = $slugger->slug($originalFilename);
                // On ajoute un ID unique pour éviter d'écraser une image existante
                $newFilename = $safeFilename.'-'.uniqid().'.'.$imageFile->guessExtension();

                try {
                    // On déplace le fichier dans le dossier configuré
                    $imageFile->move(
                        $this->getParameter('produits_directory'),
                        $newFilename
                    );
                } catch (FileException $e) {
                    // Gérer l'erreur si besoin
                }

                // On enregistre seulement le NOM du fichier dans la base
                $produit->setImageName($newFilename);
            }
            // --- FIN GESTION IMAGE ---
            $entityManager->flush();

            return $this->redirectToRoute('app_produit_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('produit/edit.html.twig', [
            'produit' => $produit,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_produit_delete', methods: ['POST'])]
    public function delete(Request $request, Produit $produit, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$produit->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($produit);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_produit_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/recherche/ajax', name: 'app_produit_recherche_ajax', methods: ['GET'])]
    public function rechercheAjax(Request $request, ProduitRepository $produitRepository): JsonResponse
    {
        $term = $request->query->get('term'); // Ce que l'utilisateur a tapé

        if (!$term) {
            return new JsonResponse([]);
        }

        // On cherche dans la BDD (Nom ou Référence)
        // Note: Tu devras peut-être adapter ta méthode findBy dans le Repository si tu veux chercher dans les deux
        // Pour l'instant, on fait simple : on cherche par nom
        $produits = $produitRepository->createQueryBuilder('p')
            ->where('p.nom LIKE :term')
            ->orWhere('p.reference LIKE :term')
            ->setParameter('term', '%' . $term . '%')
            ->getQuery()
            ->getResult();

        // On transforme les objets en tableau simple pour le JavaScript
        $results = [];
        foreach ($produits as $p) {
            $results[] = [
                'id' => $p->getId(),
                'nom' => $p->getNom(),
                'reference' => $p->getReference(),
                // On prépare l'URL de l'image si elle existe
                'image' => $p->getImageName() ? '/uploads/products/' . $p->getImageName() : null,
                'url_show' => $this->generateUrl('app_produit_show', ['id' => $p->getId()])
            ];
        }

        return new JsonResponse($results);
    }
}
