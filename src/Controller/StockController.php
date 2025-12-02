<?php

namespace App\Controller;

use App\Entity\Stock;
use App\Form\StockType;
use App\Repository\StockRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\MouvementStock;

#[Route('/stock')]
final class StockController extends AbstractController
{
    #[Route(name: 'app_stock_index', methods: ['GET'])]
    public function index(StockRepository $stockRepository): Response
    {
        return $this->render('stock/index.html.twig', [
            'stocks' => $stockRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_stock_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $stock = new Stock();
        $form = $this->createForm(StockType::class, $stock);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($stock);
            $entityManager->flush();

            return $this->redirectToRoute('app_stock_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('stock/new.html.twig', [
            'stock' => $stock,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_stock_show', methods: ['GET'])]
    public function show(Stock $stock): Response
    {
        return $this->render('stock/show.html.twig', [
            'stock' => $stock,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_stock_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Stock $stock, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(StockType::class, $stock);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_stock_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('stock/edit.html.twig', [
            'stock' => $stock,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_stock_delete', methods: ['POST'])]
    public function delete(Request $request, Stock $stock, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$stock->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($stock);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_stock_index', [], Response::HTTP_SEE_OTHER);
    }
    #[Route('/stock/mouvement/{id}/{sens}', name: 'app_stock_mouvement', methods: ['GET'])]
    public function mouvement(
        Stock $stock, 
        string $sens, 
        EntityManagerInterface $entityManager
    ): Response
    {
        // 1. Déterminer si on ajoute ou on retire
        $quantite = ($sens === 'plus') ? 1 : -1;

        // 2. Mettre à jour le stock (La table STOCK)
        $nouveauStock = $stock->getQuantite() + $quantite;
        
        // Petite sécurité : pas de stock négatif
        if ($nouveauStock < 0) {
            $this->addFlash('danger', 'Impossible ! Le stock ne peut pas être négatif.');
            return $this->redirectToRoute('app_stock_index');
        }

        $stock->setQuantite($nouveauStock);

        // 3. Créer l'historique (La table MOUVEMENT_STOCK)
        $mouvement = new \App\Entity\MouvementStock();
        $mouvement->setProduit($stock->getProduit());
        $mouvement->setEntrepot($stock->getEntrepot());
        $mouvement->setQuantite($quantite);
        $mouvement->setDateMouvement(new \DateTimeImmutable());
        
        // On définit le type (Entrée ou Sortie)
        $type = ($sens === 'plus') ? 'ENTREE_MANUELLE' : 'SORTIE_MANUELLE';
        $mouvement->setType($type);

        // 4. On sauvegarde tout ça
        $entityManager->persist($mouvement); // Prépare le mouvement
        $entityManager->flush();             // Envoie tout en base (Stock + Mouvement)

        // 5. Petit message de succès
        $this->addFlash('success', 'Stock mis à jour avec succès !');

        // 6. Retour à la liste
        return $this->redirectToRoute('app_stock_index');
    }
}
