<?php
namespace Ulogin\AuthBundle\Controller;

use Doctrine\Common\Persistence\ObjectManager;
use FOS\UserBundle\Model\UserInterface;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\AccountStatusException;
use Ulogin\AuthBundle\Entity\UloginUser;

class AuthController extends Controller
{
    public function indexAction(Request $request)
    {
        $data = $this->getUserData($_POST['token']);

        if(isset($data['error'])){
            throw new \Exception($data['error']);
        }

        $userByIdentity = $this->getDoctrine()
            ->getRepository('UloginAuthBundle:UloginUser')
            ->findOneBy(array('identity'=>$data['identity']));

        $auth_success_route = $this->container->getParameter('ulogin_auth.routing.success_auth');
        $backurl = $auth_success_route ? $this->container->get('router')->generate($auth_success_route) : urldecode($request->get('backurl'));
        $response = new RedirectResponse($backurl);

        //если пользователь уже авторизовывался на этом сайте через соцсеть
        if(!empty($userByIdentity)){

            //получаем связанного с данной соцсетью юзера
            $user = $this->container->get('fos_user.user_manager')->findUserBy(array('id'=>$userByIdentity->getUserId()));
            //если такой юзер есть - авторизуем его

            //если поддерживается photo у юзера
            if(is_callable(array($user,'setPhoto')) && is_callable(array($user,'getPhoto'))){
                if($file = $this->saveFile($data)) {
                    $user->setPhoto($file);
                    $em = $this->getDoctrine()->getManager();
                    $em->persist($user);
                    $em->flush();
                }
            }

            if(!empty($user)){

                try {
                    $this->container->get('fos_user.security.login_manager')->loginUser(
                        $this->container->getParameter('fos_user.firewall_name'),
                        $user,
                        $response);
                    return $this->container->get($this->container->getParameter('ulogin_auth.success_handler'))
                        ->onAuthenticationSuccess($request, $this->container->get('security.context')->getToken());
                } catch (AccountStatusException $ex) {
                    // We simply do not authenticate users which do not pass the user
                    // checker (not enabled, expired, etc.).
                }

            }

        } else {

            $user = $this->container->get('fos_user.user_manager')->findUserByEmail($data['email']);

            //если в системе уже зарегистрирован юзер с таким email-адресом
            if(!empty($user)){

                $uloginUser = new UloginUser();
                $uloginUser->setIdentity($data['identity']);
                $uloginUser->setNetwork($data['network']);
                $uloginUser->setUserId($user->getId());

                $em = $this->getDoctrine()->getManager();

                //если поддерживается photo у юзера
                if(is_callable(array($user,'setPhoto')) && is_callable(array($user,'getPhoto'))){
                    if($file = $this->saveFile($data)) {
                        $user->setPhoto($file);
                        $em->persist($user);
                    }
                }

                $em->persist($uloginUser);
                $em->flush();

                $this->authenticateUser($user,$response);

            //если юзер с данным email еще не зарегистрирован
            } else {

                $username = $this->generateNickname($data['first_name'], $data['last_name'], $data['nickname']);

                $user = $this->container->get('fos_user.user_manager')->createUser();
                $user->setUsername($username);
                $user->setEmail($data['email']);
                $user->setPlainPassword(md5($this->container->getParameter('ulogin_auth.secret_key').$data['identity'])); //пароль - md5(секрет_кей+identity)
                $user->setEnabled(true);
                $user->setSuperAdmin(false);
                $this->container->get('fos_user.user_manager')->updateUser($user);

                $this->authenticateUser($user,$response);
            }

        }

        return $response;
    }

    private function saveFile($data = array())
    {
        $root_path = $this->get('kernel')->getRootDir();
        $file_url = isset($data['photo_big']) ? $data['photo_big'] : (isset($data['photo']) ? $data['photo'] : false);
        $q = isset($file_url) ? true : false;

        //directory to import to
        $relativePath = $this->container->getParameter('ulogin_auth.photo_directory');
        $avatar_dir = $root_path . '/../web' . $relativePath;
        if($file_url && !file_exists($avatar_dir)) {
            mkdir($avatar_dir);
        }

        if($file_url) {
            $tmp = explode('.', $file_url);
            $ext = array_pop($tmp); // расширение
            $new_name = md5(rand() . time()) . '.' . $ext; // новое имя с расширением
            $full_path = $avatar_dir . $new_name; // полный путь с новым именем и расширением

            $tmp = $this->getResponse($file_url);
            if($tmp) {
                if(file_put_contents($full_path, $this->getResponse($file_url))){
                    return $relativePath.$new_name;
                }
            }
        }

        return false;
    }

    /**
     * Authenticate a user with Symfony Security
     *
     * @param UserInterface        $user
     * @param \Symfony\Component\HttpFoundation\Response $response
     */
    protected function authenticateUser(UserInterface $user, Response $response)
    {
        try {
            $this->container->get('fos_user.security.login_manager')->loginUser(
                $this->container->getParameter('fos_user.firewall_name'),
                $user,
                $response);
        } catch (AccountStatusException $ex) {

        }
    }

    private function getUserData($token = ''){
        $response = false;
        if ($token){
            $request = 'http://ulogin.ru/token.php?token=' . $token . '&host=' . $_SERVER['HTTP_HOST'];
            if(in_array('curl', get_loaded_extensions())){
                $c = curl_init($request);
                curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);
                $response = curl_exec($c);
                curl_close($c);

            }elseif (function_exists('file_get_contents') && ini_get('allow_url_fopen')){
                $response = file_get_contents($request);
            }
        }
        return json_decode($response,true);
    }

    private function userExist($nickname)
    {
        return $this->container->get('fos_user.user_manager')->findUserByUsername($nickname);
    }

    /**
     * @param string $first_name
     * @param string $last_name
     * @param string $nickname
     * @param string $bdate (string in format: dd.mm.yyyy)
     * @param array $delimiters
     * @return string
     */
    private function generateNickname($first_name, $last_name="", $nickname="", $bdate="", $delimiters=array('.', '_'))
    {
        $delim = array_shift($delimiters);

        $first_name = $this->translitIt($first_name);
        $first_name_s = substr($first_name, 0, 1);

        $variants = array();
        if (!empty($nickname))
            $variants[] = $nickname;
        $variants[] = $first_name;
        if (!empty($last_name)) {
            $last_name = $this->translitIt($last_name);
            $variants[] = $first_name.$delim.$last_name;
            $variants[] = $last_name.$delim.$first_name;
            $variants[] = $first_name_s.$delim.$last_name;
            $variants[] = $first_name_s.$last_name;
            $variants[] = $last_name.$delim.$first_name_s;
            $variants[] = $last_name.$first_name_s;
        }
        if (!empty($bdate)) {
            $date = explode('.', $bdate);
            $variants[] = $first_name.$date[2];
            $variants[] = $first_name.$delim.$date[2];
            $variants[] = $first_name.$date[0].$date[1];
            $variants[] = $first_name.$delim.$date[0].$date[1];
            $variants[] = $first_name.$delim.$last_name.$date[2];
            $variants[] = $first_name.$delim.$last_name.$delim.$date[2];
            $variants[] = $first_name.$delim.$last_name.$date[0].$date[1];
            $variants[] = $first_name.$delim.$last_name.$delim.$date[0].$date[1];
            $variants[] = $last_name.$delim.$first_name.$date[2];
            $variants[] = $last_name.$delim.$first_name.$delim.$date[2];
            $variants[] = $last_name.$delim.$first_name.$date[0].$date[1];
            $variants[] = $last_name.$delim.$first_name.$delim.$date[0].$date[1];
            $variants[] = $first_name_s.$delim.$last_name.$date[2];
            $variants[] = $first_name_s.$delim.$last_name.$delim.$date[2];
            $variants[] = $first_name_s.$delim.$last_name.$date[0].$date[1];
            $variants[] = $first_name_s.$delim.$last_name.$delim.$date[0].$date[1];
            $variants[] = $last_name.$delim.$first_name_s.$date[2];
            $variants[] = $last_name.$delim.$first_name_s.$delim.$date[2];
            $variants[] = $last_name.$delim.$first_name_s.$date[0].$date[1];
            $variants[] = $last_name.$delim.$first_name_s.$delim.$date[0].$date[1];
            $variants[] = $first_name_s.$last_name.$date[2];
            $variants[] = $first_name_s.$last_name.$delim.$date[2];
            $variants[] = $first_name_s.$last_name.$date[0].$date[1];
            $variants[] = $first_name_s.$last_name.$delim.$date[0].$date[1];
            $variants[] = $last_name.$first_name_s.$date[2];
            $variants[] = $last_name.$first_name_s.$delim.$date[2];
            $variants[] = $last_name.$first_name_s.$date[0].$date[1];
            $variants[] = $last_name.$first_name_s.$delim.$date[0].$date[1];
        }
        $i=0;

        $exist = true;
        while (true) {
            if ($exist = $this->userExist($variants[$i])) {
                foreach ($delimiters as $del) {
                    $replaced = str_replace($delim, $del, $variants[$i]);
                    if($replaced !== $variants[$i]){
                        $variants[$i] = $replaced;
                        if(!$exist = $this->userExist($variants[$i])){
                            break;
                        }
                    }
                }
            }
            if ($i >= count($variants)-1 || !$exist)
                break;
            $i++;
        }

        if ($exist) {
            while ($exist) {
                $nickname = $first_name.mt_rand(1, 100000);
                $exist = $this->userExist($nickname);
            }
            return $nickname;
        } else
            return $variants[$i];
    }

    private function translitIt($str) {
        $tr = array(
            "А"=>"a","Б"=>"b","В"=>"v","Г"=>"g",
            "Д"=>"d","Е"=>"e","Ж"=>"j","З"=>"z","И"=>"i",
            "Й"=>"y","К"=>"k","Л"=>"l","М"=>"m","Н"=>"n",
            "О"=>"o","П"=>"p","Р"=>"r","С"=>"s","Т"=>"t",
            "У"=>"u","Ф"=>"f","Х"=>"h","Ц"=>"ts","Ч"=>"ch",
            "Ш"=>"sh","Щ"=>"sch","Ъ"=>"","Ы"=>"yi","Ь"=>"",
            "Э"=>"e","Ю"=>"yu","Я"=>"ya","а"=>"a","б"=>"b",
            "в"=>"v","г"=>"g","д"=>"d","е"=>"e","ж"=>"j",
            "з"=>"z","и"=>"i","й"=>"y","к"=>"k","л"=>"l",
            "м"=>"m","н"=>"n","о"=>"o","п"=>"p","р"=>"r",
            "с"=>"s","т"=>"t","у"=>"u","ф"=>"f","х"=>"h",
            "ц"=>"ts","ч"=>"ch","ш"=>"sh","щ"=>"sch","ъ"=>"y",
            "ы"=>"y","ь"=>"","э"=>"e","ю"=>"yu","я"=>"ya"
        );
        if (preg_match('/[^A-Za-z0-9\_\-]/', $str)) {
            $str = strtr($str,$tr);
            $str = preg_replace('/[^A-Za-z0-9\_\-\.]/', '', $str);
        }
        return $str;
    }
}
