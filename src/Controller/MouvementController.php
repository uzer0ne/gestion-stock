<?php

namespace App\Controller;

use App\Repository\MouvementStockRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Dompdf\Dompdf;
use Dompdf\Options;
use App\Entity\MouvementStock; // Important !

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
    #[Route('/mouvement/{id}/pdf', name: 'app_mouvement_pdf')]
    public function generatePdf(MouvementStock $mouvement): void
    {
        // 1. Configurer DomPDF
        $pdfOptions = new Options();
        $pdfOptions->set('defaultFont', 'Arial');
        $dompdf = new Dompdf($pdfOptions);

        // 2. Générer le HTML (On va créer ce fichier juste après)
        // On passe le mouvement à la vue pour afficher les infos dynamiques
        $html = $this->renderView('mouvement/pdf_bon.html.twig', [
            'mouvement' => $mouvement,
            'imagePath' => $this->getParameter('kernel.project_dir') . '/public/uploads/products/'
        ]);

        // 3. Charger le HTML dans DomPDF
        $dompdf->loadHtml($html);

        // 4. Configurer la feuille (A4, Portrait)
        $dompdf->setPaper('A4', 'portrait');

        // 5. Rendre le PDF (Calculer le fichier)
        $dompdf->render();

        // 6. Envoyer le PDF au navigateur
        // "Attachment" => true signifie "Télécharger le fichier"
        // "Attachment" => false signifie "Afficher dans le navigateur"
        $dompdf->stream("bon-mouvement-{$mouvement->getId()}.pdf", [
            "Attachment" => false
        ]);
    }
}