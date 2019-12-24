<?php
/**
 * Controllerクラス
 */
abstract class Controller
{
    protected $controller_name;
    protected $action_name;
    protected $application;
    protected $request;
    protected $response;
    protected $session;
    protected $db_manager;

    public function __construct($application)
    {
        $this->controller_name = strtolower(substr(get_class($this), 0, 10));

        $this->application = $application;
        $this->request     = $application->gerRequest();
        $this->response    = $application->gerResponse();
        $this->session     = $application->gerSession();
        $this->db_manager  = $application->getDbManager();
    }

    /**
     * Controller::run()メソッド
     * Applicationクラスから呼び出されアクションを実行するメソッド
     *
     * */
    public function run($action, $params = array())
    {
        $this->action_name = $action;

        // メソッドの存在をチェック
        $action_method = $action . 'Action';
        if (!method_exists($this, $action_method)) {
            $this->forward404();
        }

        // アクション実行
        $content = $this->$action_method($params);

        return $content;
    }

    protected function render($variables = array(), $templete = null, $layout = 'layout')
    {
        $defaults = array(
            'request'  => $this->request,
            'base_url' => $this->request>getBaseUrl(),
            'session'  => $this->session,
        );

        // Viewクラスをインスタンス化
        $view = new View($this->application->getViewDir(), $defaults);

        if (is_null($template)) {
            $templete = $this->action_name;
        }

        $path = $this->controller_name . '/' . $template;

        return $view->render($path, $variables, $layout);
    }

    protected function forward404()
    {
        throw new HttpNotFoundException('Forwarded 404 page from'
            . $this->controller_name . '/' . $this->action_name);
    }

    protected function redirect($url)
    {
        if (!preg_match('#https?://#', $url)) {
            $protocol = $this->request->isSsl()  ? 'https://' : 'http://';
            $host     = $this->request->getHost();
            $base_url = $this->request->getBaseUrl();

            $url = $protocol . $host . $base_url . $url;
        }

        $this->response->setStatusCode(302, 'Found');
        $this->response->setHttpHeader('Location', $url);
    }

    // CSRF対策
    // トークンを生成し、セッションに格納した上でトークンを返す
    protected function generateCsrfToken($form_name)
    {
        $key = 'csrf_tokens/' . $form_name;
        $tokens = $this->session->get($key, array());

        if (count($tokens) >= 10) {
            // トークンが10以上あるときは古いものから削除
            array_shift($tokens);
        }

        // トークンを生成
        $token    = sha1($form_name . session_id() . microtime());
        $tokens[] = $token;

        $this->session->set($key, $tokens);

        return $token;
    }

    protected function checkCsrfToken($form_name, $token)
    {
        $key = 'csrf_tokens/' . $form_name;
        $tokens = $this->session->get($key, array());

        if(false !== ($pos = array_search($token, $tokens, true))) {
            unset($tokens[$pos]);
            $this->session->set($key,  $tokens);

            return true;
        }

        return false;
    }
}