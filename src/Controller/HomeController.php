<?php

namespace App\Controller;

use App\Repository\StockRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(StockRepository $stockRepo, ChartBuilderInterface $chartBuilder): Response
    {
        // 1. Récupérer les données brutes (Tous les stocks)
        $stocks = $stockRepo->findAll();

        // 2. Préparer les tableaux pour le graphique
        $labels = []; // Les noms des produits
        $data = [];   // Les quantités
        $colors = []; // Une couleur différente si stock critique

        foreach ($stocks as $stock) {
            // On ajoute le nom du produit + l'entrepôt en étiquette
            $labels[] = $stock->getProduit()->getNom() . ' (' . $stock->getEntrepot()->getNom() . ')';
            
            // On ajoute la quantité
            $data[] = $stock->getQuantite();

            // Gestion de la couleur (Rouge si critique, Bleu sinon)
            if ($stock->getQuantite() <= $stock->getProduit()->getSeuilAlerte()) {
                $colors[] = 'rgba(255, 99, 132, 0.5)'; // Rouge
            } else {
                $colors[] = 'rgba(54, 162, 235, 0.5)'; // Bleu
            }
        }

        // 3. Construire le graphique
        $chart = $chartBuilder->createChart(Chart::TYPE_BAR);

        $chart->setData([
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Quantité en Stock',
                    'backgroundColor' => $colors,
                    'borderColor' => 'rgb(255, 255, 255)',
                    'data' => $data,
                ],
            ],
        ]);

        $chart->setOptions([
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'suggestedMax' => 20, // Pour aérer le haut du graph
                ],
            ],
        ]);

        return $this->render('home/index.html.twig', [
            'chart' => $chart,
        ]);
    }
}