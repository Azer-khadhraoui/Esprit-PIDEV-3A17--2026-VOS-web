<?php

namespace App\Form;

use App\Dto\ClientProfileDto;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

class ClientProfileType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('imageFile', FileType::class, [
                'label' => 'Photo de profil',
                'mapped' => false,
                'required' => false,
                'attr' => [
                    'accept' => '.jpg,.jpeg,.png,.gif',
                    'class' => 'profile-file-input',
                ],
                'constraints' => [
                    new File([
                        'maxSize' => '4M',
                        'extensions' => ['jpg', 'jpeg', 'png', 'gif'],
                        'extensionsMessage' => 'Veuillez sélectionner une image valide (JPG, PNG, GIF).',
                    ]),
                ],
            ])
            ->add('nom', TextType::class, [
                'label' => 'Nom',
            ])
            ->add('prenom', TextType::class, [
                'label' => 'Prénom',
            ])
            ->add('email', EmailType::class, [
                'label' => 'Adresse e-mail',
            ])
            ->add('newPassword', PasswordType::class, [
                'label' => 'Nouveau mot de passe (optionnel)',
                'required' => false,
                'empty_data' => '',
                'attr' => [
                    'autocomplete' => 'new-password',
                    'placeholder' => 'Laisser vide pour garder le mot de passe actuel',
                ],
            ])
            ->add('confirmNewPassword', PasswordType::class, [
                'label' => 'Confirmer le nouveau mot de passe',
                'required' => false,
                'empty_data' => '',
                'attr' => [
                    'autocomplete' => 'new-password',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ClientProfileDto::class,
            'csrf_protection' => true,
        ]);
    }
}