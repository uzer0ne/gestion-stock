<?php

namespace App\Controller;

use App\Repository\MouvementStockRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class MouvementController extends AbstractController
{
    #[Route('/historique', name: 'app_historique')]
    public function index(MouvementStockRepository $mouvementRepo): Response
    {
        // On récupère TOUT, trié par date décroissante (le plus récent en haut)
        $mouvements = $mouvementRepo->findBy([], ['dateMouvement' => 'DESC']);

        return $this->render('mouvement/index.html.twig', [
            'mouvements' => $mouvements,
        ]);
    }
}