<?php

namespace App\Form;

use App\Entity\Entretien;
use App\Entity\EvaluationEntretien;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class EvaluationEntretienType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $noteChoices = array_combine(range(0, 5), range(0, 5));

        $builder
            ->add('entretien', EntityType::class, [
                'class' => Entretien::class,
                'choice_label' => static function (Entretien $entretien): string {
                    return sprintf('#%d - %s %s', $entretien->getId(), $entretien->getDateEntretien()?->format('d/m/Y') ?? '', $entretien->getTypeEntretien() ?? '');
                },
                'placeholder' => '-- Choisir un entretien --',
                'required' => true,
                'label' => 'Entretien',
            ])
            ->add('scoreTest', NumberType::class, [
                'required' => true,
                'label' => 'Score (/100)',
                'scale' => 1,
            ])
            ->add('noteEntretien', ChoiceType::class, [
                'choices' => $noteChoices,
                'required' => true,
                'placeholder' => '-- Note --',
                'label' => 'Note (/5)',
            ])
            ->add('decision', ChoiceType::class, [
                'choices' => ['Accepté' => 'Accepté', 'Refusé' => 'Refusé', 'En attente' => 'En attente'],
                'required' => true,
                'placeholder' => '-- Décision --',
                'label' => 'Décision',
            ])
            ->add('commentaire', TextareaType::class, [
                'required' => true,
                'label' => 'Commentaire',
            ])
            ->add('competencesTechniques', ChoiceType::class, [
                'choices' => $noteChoices,
                'required' => true,
                'placeholder' => '--',
                'label' => 'Compétences techniques',
            ])
            ->add('competencesComportementales', ChoiceType::class, [
                'choices' => $noteChoices,
                'required' => true,
                'placeholder' => '--',
                'label' => 'Comportementales',
            ])
            ->add('communication', ChoiceType::class, [
                'choices' => $noteChoices,
                'required' => true,
                'placeholder' => '--',
                'label' => 'Communication',
            ])
            ->add('motivation', ChoiceType::class, [
                'choices' => $noteChoices,
                'required' => true,
                'placeholder' => '--',
                'label' => 'Motivation',
            ])
            ->add('experience', ChoiceType::class, [
                'choices' => $noteChoices,
                'required' => true,
                'placeholder' => '--',
                'label' => 'Expérience',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => EvaluationEntretien::class]);
    }
}
