<?php

namespace App\Form;

use App\Entity\PreferenceCandidature;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PreferenceCandidatureType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('type_poste_souhaite')
            ->add('mode_travail')
            ->add('disponibilite')
            ->add('mobilite_geographique')
            ->add('pret_deplacement')
            ->add('type_contrat_souhaite')
            ->add('pretention_salariale')
            ->add('date_disponibilite')
            ->add('id_utilisateur')
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => PreferenceCandidature::class,
        ]);
    }
}
