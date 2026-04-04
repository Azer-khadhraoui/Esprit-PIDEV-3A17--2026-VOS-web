<?php

namespace App\Controller;

use App\Dto\SignupDto;
use App\Entity\User;
use App\Form\SignupType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

class AuthController extends AbstractController
{
    #[Route('/signup', name: 'app_signup')]
    public function signup(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher,
        UserRepository $userRepository
    ): Response {
        $signupDto = new SignupDto();
        $form = $this->createForm(SignupType::class, $signupDto);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Vérifier que les mots de passe correspondent
            if ($signupDto->password !== $signupDto->confirmPassword) {
                $this->addFlash('error', 'Les mots de passe ne correspondent pas.');
                return $this->redirectToRoute('app_signup');
            }

            // Vérifier l'unicité de l'email
            if ($userRepository->findByEmail($signupDto->email)) {
                $this->addFlash('error', 'Cet e-mail est déjà utilisé.');
                return $this->redirectToRoute('app_signup');
            }

            // Créer l'utilisateur
            $user = new User();

            $uploadedImage = $form->get('imageFile')->getData();
            if ($uploadedImage instanceof UploadedFile) {
                $uploadDirectory = $this->getParameter('kernel.project_dir') . '/public/uploads/profiles';
                $newFileName = uniqid('profile_', true) . '.' . $uploadedImage->guessExtension();

                try {
                    $uploadedImage->move($uploadDirectory, $newFileName);
                    $user->setImageProfil('/uploads/profiles/' . $newFileName);
                } catch (FileException) {
                    $this->addFlash('error', 'Impossible de téléverser la photo de profil.');
                    return $this->redirectToRoute('app_signup');
                }
            }
            
            $user->setNom((string) $signupDto->nom);
            $user->setPrenom((string) $signupDto->prenom);
            
            $user->setEmail($signupDto->email);
            $user->setRole('CLIENT');
            
            // Hasher et définir le mot de passe
            $hashedPassword = $passwordHasher->hashPassword($user, $signupDto->password);
            $user->setMotDePasse($hashedPassword);

            // Persister l'utilisateur
            $em->persist($user);
            $em->flush();

            $this->addFlash('success', sprintf('Bienvenue %s %s, votre compte a été créé avec succès !', $signupDto->prenom, $signupDto->nom));
            return $this->redirectToRoute('app_signup');
        }

        return $this->render('auth/signup.html.twig', [
            'signupForm' => $form->createView(),
        ]);
    }

    #[Route('/signin', name: 'app_signin')]
    public function signin(
        Request $request,
        UserRepository $userRepository,
        UserPasswordHasherInterface $passwordHasher,
        SessionInterface $session
    ): Response
    {
        if ($request->isMethod('POST')) {
            $email = (string) $request->request->get('email', '');
            $password = (string) $request->request->get('password', '');

            $user = $userRepository->findByEmail($email);

            if (!$user || !$passwordHasher->isPasswordValid($user, $password)) {
                $this->addFlash('error', 'Email ou mot de passe invalide.');
                return $this->redirectToRoute('app_signin');
            }

            if (!str_starts_with($user->getRole(), 'ADMIN')) {
                $this->addFlash('error', 'Accès admin uniquement.');
                return $this->redirectToRoute('app_signin');
            }

            $session->set('admin_user_id', $user->getId());
            $session->set('admin_user_role', $user->getRole());
            $session->set('admin_user_name', trim(($user->getPrenom() ?? '') . ' ' . ($user->getNom() ?? '')));

            return $this->redirectToRoute('app_admin_dashboard');
        }

        return $this->render('auth/signin.html.twig', []);
    }

    #[Route('/logout', name: 'app_logout', methods: ['POST'])]
    public function logout(SessionInterface $session): Response
    {
        $session->remove('admin_user_id');
        $session->remove('admin_user_role');
        $session->remove('admin_user_name');

        $this->addFlash('success', 'Déconnexion réussie.');

        return $this->redirectToRoute('app_signin');
    }
}
