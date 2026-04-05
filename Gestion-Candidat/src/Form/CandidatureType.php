<?php

namespace App\Form;

use App\Entity\Candidature;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CandidatureType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('date_candidature')
            ->add('statut')
            ->add('message_candidat')
            ->add('cv')
            ->add('lettre_motivation')
            ->add('niveau_experience')
            ->add('annees_experience')
            ->add('domaine_experience')
            ->add('dernier_poste')
            ->add('id_utilisateur')
            ->add('id_offre')
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Candidature::class,
        ]);
    }
}
