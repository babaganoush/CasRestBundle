<?php
namespace Main\CasRestBundle\Component\Authentication\Handler;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authentication\DefaultAuthenticationFailureHandler;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Security\Core\SecurityContext;

use Main\CasRestBundle\Controller\CasRestController;
use Main\CasRestBundle\Controller\UserManagementController;

// ...
class AuthenticationFailureHandler extends DefaultAuthenticationFailureHandler
{

   private $cas;
   private $router;
   private $userManagement;
    
public function __construct(CasRestController $cas, RouterInterface $router, UserManagementController $userManagement) 
{ 
   $this->cas = $cas;
   $this->router = $router;
   $this->userManagement= $userManagement;
}

public function onAuthenticationFailure(Request $request, AuthenticationException $exception)
{
    
$username = $request->get('_username');
$password = $request->get('_password');
    
if ($this->cas->authenticate($username, $password, FALSE))
{
   //change password if user name is in database
    $casAttributes = $this->cas->authenticate($username, $password, TRUE);
    $email = $casAttributes['EmailAddress'];
    $user = $this->userManagement->findUser($username);
   
    //create a new user if user name is not on server
    
    // TODO refactor this better
    if ($user == false){
        $rolesArr = array(1 => 'ROLE_INTERNAL_PRACTICE_USER');
        $user = $this->userManagement->registerUser($username, $email, $password, $rolesArr);
       
        if ($user == false){
           throw new \Exception("User Not Created");
        }
    }
    else{
        $this->userManagement->updateUserPassword($user, $password);
    }
     //login
    

    $this->userManagement->loginUser($username , $password); 
     
    // TODO remove this hardcoding of route
    
   $url = $this->router->generate('main_referral_capture_practice_home');
            return new RedirectResponse($url);
}
else
{
    //display message that logout failed
            $request->getSession()->set(SecurityContext::AUTHENTICATION_ERROR, $exception);
            $url = $this->router->generate('fos_user_security_login');
            return new RedirectResponse($url);
    
}
     
}
}