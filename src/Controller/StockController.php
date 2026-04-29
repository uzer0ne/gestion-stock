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
use App\Service\StockAlertService; // <-- N'oublie pas l'import de ton nouveau service !

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
    public function edit(
        Request $request, 
        Stock $stock, 
        EntityManagerInterface $entityManager,
        StockAlertService $alertService
    ): Response
    {
        // 1. On mémorise la quantité AVANT que le formulaire ne la modifie
        $ancienStock = $stock->getQuantite();

        $form = $this->createForm(StockType::class, $stock);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            // 2. On récupère la nouvelle quantité et le seuil
            $nouveauStock = $stock->getQuantite();
            $seuil = $stock->getProduit()->getSeuilAlerte();

            // 3. Vérification du franchissement de seuil
            if ($seuil !== null && $ancienStock > $seuil && $nouveauStock <= $seuil) {
                $alertService->sendAlertEmail($stock);
                $this->addFlash('warning', '⚠️ Le stock est tombé en dessous du seuil critique. Un email d\'alerte a été envoyé.');
            } else {
                $this->addFlash('success', 'Stock modifié avec succès.');
            }

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
        EntityManagerInterface $entityManager,
        StockAlertService $alertService // <-- ON INJECTE LE SERVICE D'ALERTE ICI
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
        $mouvement->setUser($this->getUser());
        
        // On définit le type (Entrée ou Sortie)
        $type = ($sens === 'plus') ? 'ENTREE_MANUELLE' : 'SORTIE_MANUELLE';
        $mouvement->setType($type);

        // 4. On sauvegarde tout ça
        $entityManager->persist($mouvement); // Prépare le mouvement
        $entityManager->flush();             // Envoie tout en base (Stock + Mouvement)

        // 5. VÉRIFICATION DU SEUIL D'ALERTE (CORRIGÉE)
        $produit = $stock->getProduit();
        $seuil = $produit->getSeuilAlerte();
        
        // On récupère l'ancien stock (avant l'opération qu'on vient de faire)
        $ancienStock = $stock->getQuantite() - $quantite;

        // Si on a un seuil ET qu'on vient tout juste de le franchir vers le bas !
        if ($seuil !== null && $ancienStock > $seuil && $nouveauStock <= $seuil) {
            
            // On envoie l'email une seule fois
            $alertService->sendAlertEmail($stock);
            
            $this->addFlash('warning', 'Stock mis à jour. ⚠️ ALERTE : Le seuil critique vient d\'être franchi, un email a été envoyé !');
            
        } else {
            // Pas d'email si on ajoute du stock ou si on était déjà en dessous du seuil
            $this->addFlash('success', 'Stock mis à jour avec succès !');
        }

        // 6. Retour à la liste
        return $this->redirectToRoute('app_stock_index');
    }

    #[Route('/stock/mouvement-masse/{id}', name: 'app_stock_mouvement_masse', methods: ['POST'])]
    public function mouvementMasse(
        Request $request, 
        Stock $stock, 
        EntityManagerInterface $entityManager,
        StockAlertService $alertService
    ): Response
    {
        // 1. Récupération des données du formulaire
        $quantiteSaisie = (int) $request->request->get('quantite');
        $action = $request->request->get('action'); // 'ajout' ou 'retrait'

        // Sécurité de base
        if ($quantiteSaisie <= 0) {
            $this->addFlash('danger', 'Veuillez saisir une quantité valide (supérieure à 0).');
            return $this->redirectToRoute('app_stock_index');
        }

        // 2. Calculs
        $ancienStock = $stock->getQuantite();
        $vraiQuantite = ($action === 'ajout') ? $quantiteSaisie : -$quantiteSaisie;
        $nouveauStock = $ancienStock + $vraiQuantite;

        if ($nouveauStock < 0) {
            $this->addFlash('danger', 'Impossible ! Le stock ne peut pas être négatif.');
            return $this->redirectToRoute('app_stock_index');
        }

        $stock->setQuantite($nouveauStock);

        // 3. Création de l'historique
        $mouvement = new \App\Entity\MouvementStock();
        $mouvement->setProduit($stock->getProduit());
        $mouvement->setEntrepot($stock->getEntrepot());
        $mouvement->setQuantite($vraiQuantite);
        $mouvement->setDateMouvement(new \DateTimeImmutable());
        $mouvement->setUser($this->getUser());
        
        // On précise que c'est un mouvement de lot
        $type = ($action === 'ajout') ? 'RECEPTION_LOT' : 'EXPEDITION_LOT';
        $mouvement->setType($type);

        $entityManager->persist($mouvement);
        $entityManager->flush();

        // 4. Gestion des alertes (Détection de franchissement)
        $seuil = $stock->getProduit()->getSeuilAlerte();
        if ($seuil !== null && $ancienStock > $seuil && $nouveauStock <= $seuil) {
            $alertService->sendAlertEmail($stock);
            $this->addFlash('warning', 'Lot traité. ⚠️ ALERTE : Le stock est passé sous le seuil critique !');
        } else {
            $this->addFlash('success', 'Le lot de ' . $quantiteSaisie . ' unité(s) a bien été traité.');
        }

        return $this->redirectToRoute('app_stock_index');
    }
}