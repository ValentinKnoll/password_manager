<?php
/*
Password manager for storing and using private passwords. 
The database is SQLite and can be stored on any external storage device.
Author: Valentin Knoll

- You need to activate the SQLite extension
- You need to activate the PDO extension

An example of starting a PHP server:
$ php -S localhost:8000 password-manager.php
$ php -S 0.0.0.0:8000 password-manager.php
*/

const DB = 'D:\e938447665.db'; //db path/name
const EXIT_COMMAND = 'TASKKILL /F /IM php.exe /T'; //For Windows systems

ob_start(); 
session_start();
error_reporting(E_ALL);

$manager = new PasswordManager(DB);
     
class PasswordManager{

  private static $DB_FILE;

  ###################################################################

  public function __construct($db){
    self::$DB_FILE = $db;
    try{
      $this->db = new PDO('sqlite:'.self::$DB_FILE);
      $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
      $this->create_db();
    }catch(PDOException $e){
      exit('<b>'.$e->getMessage().'</b>');
    }
  }

  ###################################################################

  private function create_db(){
    try{
      if(filesize(self::$DB_FILE) == 0){
        $sql = [
          "CREATE TABLE pass(
            id INTEGER PRIMARY KEY,
            url VARCHAR(200), --DEFAULT NULL
            email VARCHAR(200), --DEFAULT NULL
            login VARCHAR(200), --DEFAULT NULL
            password VARCHAR(200), --DEFAULT NULL
            notice TEXT --DEFAULT NULL
          )"
        ];
        foreach ($sql as $key) {
          $this->db->exec($key);
        }
      }
    }catch(Exception $e){
      $_SESSION['errors'][] = $e->getMessage();
    }
  }

  ###################################################################

  private function clean($arg = false){
    if(false === $arg) return false;
    if(is_array($arg)){
      $arr = [];
      foreach ($arg as $key => $value) {
        if($value || $value === '0')    
            $arr[$key] = $this->clean($value);    
      }
      return $arr;
    }
    return trim(htmlspecialchars($arg, ENT_QUOTES|ENT_HTML5, 'UTF-8'));
  }

  ###################################################################

  private function create($post){
    try{
      $post = $this->clean($post);
      if(!count($post)) throw new Exception("No parameters!", 1);
      $q = "INSERT INTO pass (url, email, login, password, notice) VALUES(".
        (empty($post['url'])? $this->db->quote(null) : $this->db->quote($post['url'])).", ".
        (empty($post['email'])? $this->db->quote(null) : $this->db->quote($post['email'])).", ".
        (empty($post['login'])? $this->db->quote(null) : $this->db->quote($post['login'])).", ".
        (empty($post['password'])? $this->db->quote(null) : $this->db->quote(base64_encode($post['password']))).", ".
        (empty($post['notice'])? $this->db->quote(null) : $this->db->quote($post['notice'])).")";
      $this->db->exec($q);
      $_SESSION['info'][] = 'Action successful!';
      $this->redirect($_SERVER['HTTP_REFERER']);
    }catch(Exception $e){
      $_SESSION['errors'][] = $e->getMessage();     
    }
    return false;
  }

  ###################################################################

  private function update($request){
    try{
      $request = $this->clean($request);
      if(empty($request['id']) || !is_numeric($request['id'])) throw new Exception("Incorrect parameters!", 1);
      if(count($request) < 3) return $this->info($request);
      $q = "UPDATE pass SET "
        ."url = ".(empty($request['url'])? $this->db->quote(null) : $this->db->quote($request['url'])).", "
        ."email = ".(empty($request['email'])? $this->db->quote(null) : $this->db->quote($request['email'])).", "
        ."login = ".(empty($request['login'])? $this->db->quote(null) : $this->db->quote($request['login'])).", "
        ."password = ".(empty($request['password'])? $this->db->quote(null) : $this->db->quote(base64_encode($request['password']))).", "
        ."notice = ".(empty($request['notice'])? $this->db->quote(null) : $this->db->quote($request['notice'])).
      " WHERE id = {$request['id']}";

      $this->db->exec($q);
      $_SESSION['info'][] = 'Action successful!';
      $this->redirect($_SERVER['HTTP_REFERER']);
    }catch(Exception $e){
      $_SESSION['errors'][] = $e->getMessage(); 
    }
    return false;
  }

  ###################################################################

  private function info($get){
    try{
      $get = $this->clean($get);
      if(empty($get['id']) || !is_numeric($get['id'])) throw new Exception("Incorrect parameters!", 1);
      $q = "SELECT * FROM pass WHERE id = {$get['id']}";
      $res = $this->db->query($q)->fetchAll(PDO::FETCH_CLASS)[0]?? false;
      if(empty($res)) $this->redirect();
      return $res;  
    }catch(Exception $e){
      $_SESSION['errors'][] = $e->getMessage(); 
    }
    return false;
  }

  ###################################################################

  private function delete($get){
    try{
      $get = $this->clean($get);
      if(empty($get['id']) || !is_numeric($get['id'])) throw new Exception("Incorrect parameters!", 1);
      $q = "DELETE FROM pass WHERE id = {$get['id']}";
      $this->db->exec($q);
      $_SESSION['info'][] = 'Action successful!';
      $this->redirect();
    }catch(Exception $e){
      $_SESSION['errors'][] = $e->getMessage(); 
    }
    return false;
  }

  ###################################################################

  private function search($get){
    try{
      $get = $this->clean($get);
      if(empty($get['search_name'])) return false;
      if(strlen($get['search_name']) < 3) throw new Exception("Enter at least 3 characters!", 1);
      $get['search_name'] = substr($get['search_name'], 0, 20);
      $q = "SELECT * FROM pass WHERE url LIKE '%{$get['search_name']}%' OR (email LIKE '%{$get['search_name']}%') OR (login LIKE '%{$get['search_name']}%')";

      $res = $this->db->query($q)->fetchAll(PDO::FETCH_CLASS);
      if(empty($res)) throw new Exception("No search results!", 1);
      $_SESSION['info'][] = 'Search results for: '.$get['search_name'];
      return $res;  
    }catch(Exception $e){
      $_SESSION['errors'][] = $e->getMessage();   
    }
    return false;
  }

  ###################################################################

  public function get_count(){
    try{
      $q = "SELECT COUNT(*) as count FROM pass";
      $res = $this->db->query($q)->fetchAll(PDO::FETCH_CLASS)[0]->count;
      return $res; 
    }catch(Exception $e){
      $_SESSION['errors'][] = $e->getMessage();    
    }
  }

  ###################################################################

  private function show($get = []){
    try{
      $get = $this->clean($get);
      $from = (isset($get['from']) && is_numeric($get['from']))? $get['from'] : 0;
      $q = "SELECT * FROM pass ORDER BY url LIMIT {$from}, 10";
      $res = $this->db->query($q)->fetchAll(PDO::FETCH_CLASS);
      $arr[1] = empty($res)? false : $res;
      $arr[2] = $from;
      return $arr; 
    }catch(Exception $e){
      $_SESSION['errors'][] = $e->getMessage();    
    }
    return false;
  }

  ###################################################################

  public function init(){
    $route = $_GET['route']?? '/';

    switch ($route) {
      case 'create':
        $_SESSION['tpl'] = $route;
        if($_SERVER['REQUEST_METHOD'] == 'POST') return $this->create($_POST);
        break;
      case 'update':
        $_SESSION['tpl'] = $route;
        if($_SERVER['REQUEST_METHOD'] == 'POST' || $_SERVER['REQUEST_METHOD'] == 'GET') return $this->update($_REQUEST);
        break;
      case 'delete':
        $_SESSION['tpl'] = $route;
        if($_SERVER['REQUEST_METHOD'] == 'GET') return $this->delete($_GET);
        break;
      case 'search':
        $_SESSION['tpl'] = $route;
        if($_SERVER['REQUEST_METHOD'] == 'GET') return $this->search($_GET);
        break;
      case 'info':
        $_SESSION['tpl'] = $route;
        if($_SERVER['REQUEST_METHOD'] == 'GET') return $this->info($_GET);
        break;
      case 'stop_server':
        unset($_SESSION['tpl']);
        exec(EXIT_COMMAND);
        break;
      default:
        $_SESSION['tpl'] = 'main';
        return $this->show($_GET);
        break;
    }
    return false;
  }

  ###################################################################

  public function show_errors(){
    if(!empty($_SESSION['errors'])){
      foreach ($_SESSION['errors'] as $key => $value) {
        echo "<p data-role='info' class='text-danger'>{$value}</p>";
      }
      $_SESSION['errors'] = [];
    }
  }

  public function show_info(){
    if(!empty($_SESSION['info'])){
      foreach ($_SESSION['info'] as $key => $value) {
        echo "<p data-role='info' class='text-success'>{$value}</p>";
      }
      $_SESSION['info'] = [];
    }
  }
  ###################################################################

  private function redirect($url = '/'){
    header('Location: '.$url);
    exit;
  }

  ###################################################################
}
?>

<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="Password Manager" content="">
    <title>Password-Manager</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-1BmE4kWBq78iYhFldvKuhfTAU6auU8tT94WrHftjDbrCEXSU1oBoqyl2QvZ6jIW3" crossorigin="anonymous">
    <style>
      main{min-height: 85vh;}
      #_info{min-height: 3rem;}
      .bd-placeholder-img {
        font-size: 1.125rem;
        text-anchor: middle;
        -webkit-user-select: none;
        -moz-user-select: none;
        user-select: none;
      }
      @media (min-width: 768px) {
        .bd-placeholder-img-lg {
          font-size: 3.5rem;
        }
      }
    </style>  
  </head>
  <body class="bg-dark">
    
<main>
<section class="pb-1 pt-5 container text-white bg-dark">
            <p class="text-end"><a href="?route=stop_server" class="btn btn-danger btn-sm confirm">Stop Server</a></p>
          <h4 class="text-white">Password-Manager</h4>
          <p class="text-muted"><?php echo $manager->get_count(); ?> - Entries in database.</p>
          <ul class="list-unstyled">
            <li><a href="/" class="text-warning">All passwords</a></li>
            <li><a href="?route=create" class="text-warning">Add new entry</a></li>
            <li><a href="?route=search" class="text-warning">Search</a></li>
          </ul>
</section>
<section id="_info" class="container bg-dark"></section>
<?php
$info_img = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-info-square" viewBox="0 0 16 16">
  <path d="M14 1a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H2a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1h12zM2 0a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V2a2 2 0 0 0-2-2H2z"/>
  <path d="m8.93 6.588-2.29.287-.082.38.45.083c.294.07.352.176.288.469l-.738 3.468c-.194.897.105 1.319.808 1.319.545 0 1.178-.252 1.465-.598l.088-.416c-.2.176-.492.246-.686.246-.275 0-.375-.193-.304-.533L8.93 6.588zM9 4.5a1 1 0 1 1-2 0 1 1 0 0 1 2 0z"/></svg>';
$data = $manager->init();

switch ($_SESSION['tpl']) {
  case 'create':################################---CREATE---##################################
?>
  <section class="py-1 container text-white bg-dark">
    <h1>Add new entry</h1>
    <form action="?route=create" method="POST">
      <div class="mb-3">
        <label for="url" class="form-label">URL</label>
        <input type="text" class="form-control" name="url" id="url" placeholder="http//: ..." value="<?php echo $_POST['url']?? ''; ?>">
      </div>
      <div class="mb-3">
        <label for="email" class="form-label">Email</label>
        <input type="text" class="form-control" name="email" id="email" placeholder="name@example.com" value="<?php echo $_POST['email']?? ''; ?>">
      </div>
      <div class="mb-3">
        <label for="login" class="form-label">Login</label>
        <input type="text" class="form-control" name="login" id="login" value="<?php echo $_POST['login']?? ''; ?>">
      </div>
      <div class="mb-3">
        <label for="password" class="form-label">Password</label>
        <input type="text" class="form-control" name="password" id="password" value="<?php echo $_POST['password']?? ''; ?>">
      </div>
      <div class="mb-3">
        <label for="description" class="form-label">Notice</label>
        <textarea class="form-control" name="notice" id="description" rows="6"><?php echo $_POST['notice']?? ''; ?></textarea>
      </div>
      <a class="btn btn-sm btn-warning" href="/" role="button">Back</a>
      <button type="submit" class="btn btn-primary btn-sm">Save</button>
    </form>
  </section>
<?php
    break;
  case 'search':###############################---SEARCH---##################################
?>
  <?php if($data){ $i = 0; ?>
  <section class="py-1 container bg-dark">
    <div class="table-responsive">
    <table class="table table-dark table-hover">
      <thead>
        <tr>
          <th scope="col">#</th>
          <th scope="col">Info</th>
          <th scope="col">URL</th>
          <th scope="col">Email</th>
          <th scope="col">Login</th>
          <th scope="col">Password</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($data as $key => $value) { ?>
          <tr>
            <th scope="row"><?php echo ++$i; ?></th>
            <td><a class="text-warning" href="?route=info&id=<?php echo $value->id; ?>"><?php echo $info_img?? 'Link'; ?></a></td>
            <td><?php echo $value->url; ?></td>
            <td><?php echo $value->email; ?></td>
            <td><?php echo $value->login; ?></td>
            <td><?php echo base64_decode($value->password); ?></td>
          </tr>
        <?php } ?>
      </tbody>
    </table>
    </div>
    <a class="btn btn-sm btn-warning" href="?route=search" role="button">Back</a>
  </section>
  <?php }else{ ?>
  <section class="py-1 container text-white bg-dark">
    <h1>Search</h1>
    <form>
      <input type="hidden" name="route" value="search">
      <div class="mb-3">
        <input type="text" class="form-control" name="search_name" id="name" placeholder="Min 3 characters" value="<?php echo $_GET['search_name']?? ''; ?>">
      </div>
      <a class="btn btn-sm btn-warning" href="/" role="button">Back</a>
      <button type="submit" class="btn btn-warning btn-sm">Search</button>
    </form>
  </section>
  <?php } ?>
<?php
    break;
  case 'info':###############################---INFO---##################################
?>
  <section class="py-1 container text-white bg-dark">
      <h1>Info</h1>
      <div class="mb-3">
        <label class="form-label">URL</label>
        <input readonly type="text" class="form-control bg-secondary text-white" value="<?php echo $data->url; ?>">
      </div>
      <div class="mb-3">
        <label class="form-label">Email</label>
        <input readonly type="text" class="form-control bg-secondary text-white" value="<?php echo $data->email; ?>">
      </div>
      <div class="mb-3">
        <label class="form-label">Login</label>
        <input readonly type="text" class="form-control bg-secondary text-white" value="<?php echo $data->login; ?>">
      </div>
      <div class="mb-3">
        <label class="form-label">Password</label>
        <input readonly type="text" class="form-control bg-secondary text-white" value="<?php echo base64_decode($data->password); ?>">
      </div>
      <div class="mb-3">
        <label class="form-label">Notice</label>
        <textarea readonly class="form-control bg-secondary text-white" rows="6"><?php echo $data->notice?? ''; ?></textarea>
      </div>
      <a class="btn btn-sm btn-warning" href="/" role="button">Back</a>
      <a class="btn btn-sm btn-success" href="?route=update&id=<?php echo $data->id; ?>" role="button">Edit</a>
      <a data-msg="<?php echo $data->url? "Attention! \nRemove password for {$data->url}.":""; ?>" class="confirm btn btn-sm btn-danger" href="?route=delete&id=<?php echo $data->id; ?>" role="button">Delete</a>
  </section>
  <div hidden id="info_label" class="bg-warning px-2">Click to copy</div>
<?php
    break;
  case 'update':###############################---EDIT---##################################
?>
  <section class="py-1 container text-white bg-dark">
    <h1>Edit</h1>
    <form action="?route=update&id=<?php echo $data->id?? ''; ?>" method="POST">
      <div class="mb-3">
        <label for="u_url" class="form-label">URL</label>
        <input type="text" class="form-control" name="url" id="u_url" placeholder="http//: ..." value="<?php echo $_POST['url']?? $data->url?? '' ; ?>">
      </div>
      <div class="mb-3">
        <label for="u_email" class="form-label">Email</label>
        <input type="text" class="form-control" name="email" id="u_email" placeholder="name@example.com" value="<?php echo $_POST['email']?? $data->email?? ''; ?>">
      </div>
      <div class="mb-3">
        <label for="u_login" class="form-label">Login</label>
        <input type="text" class="form-control" name="login" id="u_login" value="<?php echo $_POST['login']?? $data->login?? ''; ?>">
      </div>
      <div class="mb-3">
        <label for="u_password" class="form-label">Password</label>
        <input type="text" class="form-control" name="password" id="u_password" value="<?php echo $_POST['password']?? base64_decode($data->password?? ''); ?>">
      </div>
      <div class="mb-3">
        <label for="u_notice" class="form-label">Notice</label>
        <textarea class="form-control" name="notice" id="u_notice" rows="6"><?php echo $_POST['notice']?? $data->notice?? ''; ?></textarea>
      </div>
      <a class="btn btn-sm btn-warning" href="?route=info&id=<?php echo $data->id; ?>" role="button">Back</a>
      <button type="submit" class="btn btn-primary btn-sm">Save</button>
    </form>   
  </section>
<?php
    break;
  default:################################---DEFAULT---##################################
?>
  <?php if($data[1]){ $i = $data[2]; ?>
  <section class="py-1 container bg-dark">
    <div class="table-responsive">
    <table class="table table-dark table-hover">
      <thead>
        <tr>
          <th scope="col">#</th>
          <th scope="col">Info</th>
          <th scope="col">URL</th>
          <th scope="col">Email</th>
          <th scope="col">Login</th>
          <th scope="col">Password</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($data[1] as $key => $value) { ?>
          <tr>
            <th scope="row"><?php echo ++$i; ?></th>
            <td><a class="text-warning" href="?route=info&id=<?php echo $value->id; ?>"><?php echo $info_img?? 'Link'; ?></a></td>
            <td><?php echo $value->url; ?></td>
            <td><?php echo $value->email; ?></td>
            <td><?php echo $value->login; ?></td>
            <td><?php echo base64_decode($value->password); ?></td>
          </tr>
        <?php } ?>
      </tbody>
    </table>
    </div>
  </section>
  <section class="py-1 container text-white bg-dark">
  <?php  
    $count = $manager->get_count();
    $count = ceil($count / 10);
    for ($i=1; $i <= $count; $i++) { 
      $from = $i * 10 - 10;
      $style = ($data[2] / 10 + 1) == $i? 'text-primary' : 'text-warning';
      echo "<a class='px-2 {$style}' href='/?from={$from}'>{$i}</a> |";
    }
  ?>
  </section>
  <?php }else{ ?>
  <section class="py-1 container text-white bg-dark">
    <h1>No entries!</h1>
  </section>
  <?php } ?>
<?php
    break;
}?>

<div data-info hidden>
  <?php $manager->show_errors(); ?>
  <?php $manager->show_info(); ?>
</div>

</main>

<footer class="text-muted pb-1 pt-5">
  <div class="container">
    <p class="text-end"><a href="#" class="text-warning">Back to top</a></p>
<p class="text-center"><a class="text-warning text-decoration-none" target="_blank" href="https://wunder-webworld.com/">Wunder-Webworld</a></p>
  </div>
</footer>


    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-ka7Sk0Gln4gmtz2MlQnikT1wXgYsOg+OMhuP+IlRH9sENBO0LRn5q+8nbTov4+1p" crossorigin="anonymous"></script>
    <script>
      document.addEventListener('DOMContentLoaded', () => {
          'use strict'
          let infos = document.querySelector("[data-info]")
          let info = document.querySelector('#_info')
          info.innerHTML = infos.innerHTML
          setTimeout(() => info.innerHTML = '', 3000)
          let confirms = document.querySelectorAll('.confirm')
          if (confirms) confirms.forEach(el => {
              el.addEventListener('click', (e) => {
                  e.preventDefault()
                  let i = el.dataset.msg ? `${el.dataset.msg} \n\n Do you really want to carry that out?` : `Do you really want to carry that out?`
                  if (confirm(i)) window.location.replace(e.target.href)
              })
          })

          // Copy text to buffer
          function copy_text(btn, source) {
              if (!btn || !source) return
              btn.addEventListener('click', (e) => {
                  if (source.value) {
                      source.select()
                      source.setSelectionRange(0, 99999) /* For mobile devices */
                      document.execCommand("copy")
                  } else {
                      let x = document.createElement('TEXTAREA')
                      x.textContent = source.textContent
                      x.className = '__f__'
                      document.body.appendChild(x)
                      document.querySelector('.__f__').select()
                      document.execCommand('copy')
                      document.querySelector('.__f__').remove()
                  }

                  let confirm = document.createElement('div')
                  confirm.className = '__c__'
                  confirm.style.cssText = 'position: fixed; top:50%; left: 50%; transform:translate(-50%,-50%); color:#000; font-size:2rem; padding: .2em; background-color: #FFCA2C;'
                  confirm.textContent = 'Copied!'
                  document.body.appendChild(confirm)
                  setTimeout(() => {
                      source.classList.remove('text-warning')
                      document.querySelector('.__c__').remove()
                  }, 1000)
              })
          }

          function CopyEffect() {
              try {
                  let info_inputs = document.querySelectorAll('[readonly]')
                  let info_label = document.querySelector('#info_label')
                  if (!info_inputs || !info_label) return
                  info_label.style.position = 'absolute'

                  info_inputs.forEach(el => {
                      if (el.value.trim()) {
                          el.style.cursor = 'pointer'
                          el.addEventListener('mousemove', (e) => {
                              info_label.style.left = (e.pageX + info_label.clientWidth / 10) + 'px'
                              info_label.style.top = (e.pageY - 30) + 'px'
                              info_label.hidden = false
                          })
                          el.addEventListener('mouseout', () => info_label.hidden = true)
                          copy_text(el, el)
                      }
                  })
              } catch (e) {
                  console.log(e)
              }
          }

          CopyEffect()
      })
    </script>
      
  </body>
</html>

<?php ob_end_flush(); ?>
