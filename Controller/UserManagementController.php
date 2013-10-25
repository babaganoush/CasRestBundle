<?php

namespace Main\CasRestBundle\Controller;

use Symfony\Component\HttpFoundation\Response;
use FOS\UserBundle\Doctrine\UserManager;
use FOS\UserBundle\Model\User;
use Symfony\Component\Security\Core\Encoder\EncoderFactory;
use Symfony\Component\Security\Core\SecurityContext;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authentication\Token\AnonymousToken;
use Symfony\Component\HttpFoundation\Request;


class UserManagementController{
    protected $userManager;
    protected $encoderFactory;
    protected $securityContext;
    protected $firewallName;
    protected $error;
    
    
    public function __construct(UserManager $userManager, EncoderFactory $encoderFactory, SecurityContext $securityContext, $firewallName) { 
        
        $this->userManager = $userManager;
        $this->encoderFactory = $encoderFactory;
        $this->securityContext = $securityContext;
        $this->firewallName = $firewallName;
       
        }
    
    protected function getUserManager(){
            return $this->userManager;
        }

    protected function loginUserEntity(User $user){
            $security = $this->securityContext;
            $providerKey = $this->firewallName;
            $roles = $user->getRoles();
            $token = new UsernamePasswordToken($user, null, $providerKey, $roles);
            $security->setToken($token);
            
        }

     protected function checkUserPassword(User $user, $password){
            $factory = $this->encoderFactory;
            $encoder = $factory->getEncoder($user);
            if(!$encoder){
                return false;
            }
            return $encoder->isPasswordValid($user->getPassword(), $password, $user->getSalt());
        }
    
    
    public function loginUser($username, $password){
        
            $user = $this->findUser($username);
            if(!$user instanceof User){  
            return false;
            }
            if(!$this->checkUserPassword($user, $password)){
                return false;
            }
            $this->loginUserEntity($user);
           return true;
        }
    

    public function registerUser($username, $email, $password, $roles){           
             $userManager = $this->getUserManager();

                $user = $this->findUser($username);

             if(!$user instanceof User){    
                $user = $userManager->createUser();
                $user->setEmail($email);
                $user->setUsername($username);
                $user->setPlainPassword($password);
                $user->setEnabled(true);
                $user->setRoles($roles);
                $userManager->updateUser($user);
               return $user;

            }
            else{
               return false;
            }

        }
    
    
    public function findUser($username){
     
       $userManager = $this->getUserManager();
       $user = $userManager->findUserByUsernameOrEmail($username);
         if(!$user instanceof User){
            return false;
        }
        
        return $user;   
    }
    
    
    public function updateUserPassword(User $user, $password){
     
            $userManager = $this->getUserManager();
            $user->setPlainPassword($password);
            $userManager->updateUser($user);  
        }
    
}