<?php


namespace App\Service;

use App\Entity\Stock;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;

class StockAlertService
{
    private MailerInterface $mailer;

    // On injecte le Mailer de Symfony dans notre service
    public function __construct(MailerInterface $mailer)
    {
        $this->mailer = $mailer;
    }

    public function sendAlertEmail(Stock $stock): void
    {
        $produit = $stock->getProduit();

        // Création d'un email basé sur un template Twig
        $email = (new TemplatedEmail())
            ->from('ne-pas-repondre@stockflow.fr')
            ->to('charlesyaraazis@gmail.com') // L'email de l'administrateur
            ->subject('⚠️ ALERTE : Stock critique pour ' . $produit->getNom())
            ->htmlTemplate('emails/stock_alert.html.twig') // Le chemin vers notre template Twig
            ->context([
                'produit' => $produit,
                'stock' => $stock,
            ]);

        // Envoi de l'email
        $this->mailer->send($email);
    }
}