<?php

namespace App\Form;

use App\Entity\EvaluationEntretien;
use App\Entity\Entretien;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class EvaluationEntretienType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $noteChoices = array_combine(range(0, 5), range(0, 5));

        $builder
            ->add('entretien', EntityType::class, [
                'class'        => Entretien::class,
                'choice_label' => function (Entretien $e) {
                    return sprintf('#%d — %s %s', $e->getId(), $e->getDateEntretien()?->format('d/m/Y') ?? '', $e->getTypeEntretien() ?? '');
                },
                'placeholder'  => '-- Choisir un entretien --',
                'required'     => true,
                'label'        => 'Entretien',
                'constraints'  => [new Assert\NotNull(message: 'Veuillez sélectionner un entretien.')],
            ])
            ->add('scoreTest', NumberType::class, [
                'required'    => true,
                'label'       => 'Score (/100)',
                'scale'       => 1,
                'constraints' => [
                    new Assert\NotBlank(message: 'Le score est obligatoire.'),
                    new Assert\Range(['min' => 0, 'max' => 100, 'notInRangeMessage' => 'Le score doit être entre {{ min }} et {{ max }}.']),
                ],
            ])
            ->add('noteEntretien', ChoiceType::class, [
                'choices'     => $noteChoices,
                'required'    => true,
                'placeholder' => '-- Note --',
                'label'       => 'Note (/5)',
                'constraints' => [new Assert\NotNull(message: 'La note est obligatoire.')],
            ])
            ->add('decision', ChoiceType::class, [
                'choices'     => ['Accepté' => 'Accepté', 'Refusé' => 'Refusé', 'En attente' => 'En attente'],
                'required'    => true,
                'placeholder' => '-- Décision --',
                'label'       => 'Décision',
                'constraints' => [new Assert\NotBlank(message: 'La décision est obligatoire.')],
            ])
            ->add('commentaire', TextareaType::class, [
                'required'    => true,
                'label'       => 'Commentaire',
                'constraints' => [
                    new Assert\NotBlank(message: 'Le commentaire est obligatoire.'),
                    new Assert\Length(['min' => 5, 'max' => 1000, 'minMessage' => 'Minimum {{ limit }} caractères.', 'maxMessage' => 'Maximum {{ limit }} caractères.']),
                ],
            ])
            ->add('competencesTechniques', ChoiceType::class, ['choices' => $noteChoices, 'required' => true, 'placeholder' => '--', 'label' => 'Compétences techniques', 'constraints' => [new Assert\NotNull(message: 'Ce champ est obligatoire.')]])
            ->add('competencesComportementales', ChoiceType::class, ['choices' => $noteChoices, 'required' => true, 'placeholder' => '--', 'label' => 'Comportementales', 'constraints' => [new Assert\NotNull(message: 'Ce champ est obligatoire.')]])
            ->add('communication', ChoiceType::class, ['choices' => $noteChoices, 'required' => true, 'placeholder' => '--', 'label' => 'Communication', 'constraints' => [new Assert\NotNull(message: 'Ce champ est obligatoire.')]])
            ->add('motivation', ChoiceType::class, ['choices' => $noteChoices, 'required' => true, 'placeholder' => '--', 'label' => 'Motivation', 'constraints' => [new Assert\NotNull(message: 'Ce champ est obligatoire.')]])
            ->add('experience', ChoiceType::class, ['choices' => $noteChoices, 'required' => true, 'placeholder' => '--', 'label' => 'Expérience', 'constraints' => [new Assert\NotNull(message: 'Ce champ est obligatoire.')]])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => EvaluationEntretien::class]);
    }
}
