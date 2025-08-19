<?php

namespace App\Twig;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
use App\Entity\Settings;

class ContactsExtension extends AbstractExtension
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('contacts', [$this, 'contacts'], ['needs_environment' => true, 'is_safe' => ['html']]),
        ];
    }

    public function contacts(Environment $twig, string $val):string
    {
        $data = $this->em->getRepository(Settings::class)->findOneBy(['name' => $val]);

        if($data) {
            $contact = $data->getValue();
        } else {
            $contact = '';
        }

        return $contact;
    }

}