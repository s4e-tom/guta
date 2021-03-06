<?php

use Phalcon\Mvc\Controller;

class FilesController extends Controller
{
    public function initialize()
    {
        $this->assets
            ->addCss("css/bootstrap.min.css")
            ->addCss("css/design.css")
            ->addCss("css/dropzone.css");

        $this->assets
            ->addJs("js/jquery-1.11.2.min.js")
            ->addJS("js/bootstrap.min.js")
            ->addJs("js/dropzone.js")
            ->addJs("js/showFile.js")
            ->addJs("js/contextMenu.js")
            ->addJs("js/share.js");

        \Phalcon\Tag::setTitle('MyDropbox');

        $ds = DIRECTORY_SEPARATOR;  // '/'
        $storeFolder = 'uploadedFiles';   // the folder where we store all the files
        $user = $this->session->get('auth')['idUser'];
        $userPath = dirname( __FILE__ ) . $ds . '..' . $ds . '..' . $ds . '..' . $ds . $storeFolder . $ds . $user. $ds;

        $this->persistent->userPath = $userPath;
    }

    public function indexAction()
    {
    }

    public function uploadAction($directory = null)
    {
        $ds          = DIRECTORY_SEPARATOR;  // '/'
       
        // Get the folder path  with userPath as the folder root for the user.
        $pos = strpos(urldecode($_SERVER['REQUEST_URI']),$directory);
        if($pos)
            $directory = urldecode(substr($_SERVER['REQUEST_URI'], $pos));  

        if (!empty($_FILES)) {
             
            $tempFile = $_FILES['file']['tmp_name'];
            $targetPath = $this->persistent->userPath . urldecode($directory) . $ds;
            $targetFile =  $targetPath. $_FILES['file']['name'];
         
            move_uploaded_file($tempFile,$targetFile);
            
            chdir($targetPath); 
            exec("svn add \"".$targetFile."\"");
            exec("svn commit -m \"uploaded file\"");
            exec("svn up --accept mine-full");
        }
        return $this->dispatcher->forward(array(
            "controller" => "files",
            "action" => "list"
        ));
    }

    public function deleteAction($fileName){
        $fileName = str_replace('¤', '\\', $fileName);
        $ds = DIRECTORY_SEPARATOR;
        $storeFolder = "uploadedFiles"; //same as upload
        $user = $this->session->get('auth')['idUser'];
        $folder=".." . $ds . ".." . $ds . $storeFolder . $ds . $user;
        if(file_exists(realpath($folder.$ds.$fileName)))
        {
            //First, we check if it's a shared file/dir and delete all notifications about it
            $query = Sharedfile::findByPath(str_replace('\\', '/', $fileName));
            foreach ($query as $sharedPath) {
                $queryNotif = Notification::findById_SharedFile($sharedPath->idShared_File);
                foreach ($queryNotif as $notification) {
                    $notification->delete();
                }
                $sharedPath->delete();
            }

            
            chdir($folder);
            if(is_dir($fileName)){
                exec("svn rm \"".addslashes($fileName)."\"");
                exec("svn commit -m \"removed directory\"");
                exec("svn up --accept mine-full");

                //Redirection to stay in folder view
                $names = explode('\\', $fileName);
                array_pop($names);
                array_pop($names);
                $folderView = implode('\\', $names);
                $this->response->redirect("files/list/".$folderView);
                return;
            }
            else{
                exec("svn rm \"".addslashes($fileName)."\"");
                exec("svn commit -m \"removed file\"");
                exec("svn up --accept mine-full");

                //Redirection to stay in folder view
                $names = explode('\\', $fileName);
                array_pop($names);
                $folderView = implode('\\', $names);
                $this->response->redirect("files/list/".$folderView);
                return;
            }
        }
    }

    public function downloadAction($fileName)
    {
        $this->view->disable();

        $fileName = str_replace('¤', '\\', $fileName);
        $ds = DIRECTORY_SEPARATOR;
        $storeFolder = "uploadedFiles"; //same as upload
        $user = $this->session->get('auth')['idUser'];
        //Force the download of a file
        $file = ".." . $ds . ".." . $ds . $storeFolder . $ds;
        // Check if we are in a another directory and changes the path accordingly
        if(is_numeric(current(explode('\\', $fileName))))
            $file .= $fileName;
        else
            $file .= $user . $ds . $fileName;

        $length   = sprintf("%u", filesize($file));

        if(file_exists(realpath($file)))
        {   
            if(is_dir(realpath($file))){
                // filename for the browser to save the zip file
                header("Content-Type: application/x-tar");
                header("Content-Disposition: attachment; filename='".basename($file).".zip'");
                header("Content-Transfer-Encoding: binary");

                // get a tmp name for the .zip
                $tmp_zip = tempnam ("tmp", "tempname") . ".zip";

                //change directory so the zip file doesnt have a tree structure in it.
                chdir($file);
               
                // zip the stuff (dir and all in there) into the tmp_zip file
                exec('zip -R '.$tmp_zip.' *');
               
                // calc the length of the zip. it is needed for the progress bar of the browser
                $filesize = filesize($tmp_zip);
                header("Content-Length: $filesize");

                // deliver the zip file
                $fp = fopen("$tmp_zip","r");
                echo fpassthru($fp);

                // clean up the tmp zip file
                unlink($tmp_zip);
                exit;
            }
            else{
                header('Content-Description: File Transfer');
                header('Content-Type: application/octet-stream');
                header('Content-Disposition: attachment; filename='.basename($file));
                header('Expires: 0');
                header('Cache-Control: must-revalidate');
                header('Pragma: public');
                header('Content-Length: ' . $length);
                readfile($file);
                error_log($file);
                exit;
            }
        }
        else
        {
            $names = explode('\\', $fileName);
            array_pop($names);
            $folderView = implode('\\', $names);
            $this->response->redirect("files/list/".$folderView);
            return;
        }
    }

    private function getDirSize($path)
    {
        $size = 0;
        $files = scandir($path);

        foreach ($files as $file) {
            if(is_dir($path . "/" . $file)) {
                $newPath = $path . "/" . $file;
                if($file != ".." && $file != ".")
                    $size += $this->getDirSize($newPath);
            } else {
                $size += $this->getFileSize(filesize($path . "/" . $file));
            }
        }
        return $size;
    }

    private function getFileSize($bytes, $decimals = 2) {
        $size = array('o','ko','Mo','Go','To');
        $factor = floor((strlen($bytes) - 1) / 3);
        return sprintf("%.{$decimals}f ", $bytes / pow(1024, $factor)) . @$size[$factor];
    }

    public function listAction($directory = null)
    {
        $directoryArray = array();
        $dirArray = array();
        $fileArray = array();
        $sharedFiles = array();
        $sharedDirectories = array();

        $owner = null;
        $inSharedDirectory = false;

        // Get the folder path  with userPath as the folder root for the user.
        $pos = strpos(urldecode($_SERVER['REQUEST_URI']),$directory);
        if($pos)
            $directory = urldecode(substr($_SERVER['REQUEST_URI'], $pos));  
        
        // Checking if in a shared directory and redirecting the path accordingly
        if(is_numeric(current(explode('/', $directory)))) {
            $owner = current(explode('/', $directory));
            $inSharedDirectory = true;
            $pathDirectory = urldecode($this->persistent->userPath . "../" . $directory);
        }
        else {
            $pathDirectory = urldecode($this->persistent->userPath . $directory);
        }

        // Checking if we can go in a shared directory
        if($owner != null){
            $query = $this->modelsManager->createBuilder()
                ->from('Sharedfile')
                ->where('id_owner = :owner: AND id_user = :user:', ['owner' => $owner, 'user' => $this->session->get('auth')['idUser']])
                ->getQuery();
            $result = $query->execute(array(
                'owner' => $owner,
                'user' => $this->session->get('auth')['idUser'],
            ));
            $accessGranted = false;
            foreach ($result as $sharedPath) {
                $string = $sharedPath->id_owner . "/" . $sharedPath->path ;
                if(substr_compare($string, $directory, 0, strlen($string)) == 0){
                    $accessGranted = true;
                    break;
                }
            }
        }
        else
            $accessGranted = true;
        if(!is_dir($pathDirectory) || !$accessGranted || $directory == '.svn') {
            $this->response->redirect("files/list/");
            return;
        }

        $files = scandir($pathDirectory);
        $directory = rtrim(ltrim($directory, '/'), '/');
        //Searching for files/directories in directory and putting them in arrays for view
        foreach ($files as $file) {            
            if($file != '.' && $file != '.svn'){
                if (is_dir($pathDirectory . "/" . $file)) {
                    $size = null;
                    if(!(strlen($directory) == 0 && $file == "..")){
                        if($file != ".." && $file != ".")
                            $size = $this->getDirSize($pathDirectory . "/" . $file);
                        else
                            $size = null;
                        if(!$inSharedDirectory)
                            array_push($dirArray, array('name' => $file, 'size' => $size));
                        else
                            array_push($sharedDirectories, array('realPath' => $file, 'name' => $file, 'size' => $size));
                    }
                } else {
                    $size = $this->getFileSize(filesize($pathDirectory . "/" . $file));
                    $modifyDate = date ("d/m/Y H:i:s.", filemtime($pathDirectory . "/" . $file));
                    if(!$inSharedDirectory)
                        array_push($fileArray, array('name' => $file, 'size' => $size, 'modifyDate' => $modifyDate));
                    else
                        array_push($sharedFiles, array('realPath' => $file, 'name' => $file, 'size' => $size, 'modifyDate' => $modifyDate));
                }
            }
        }
        //Searching for the shared files/directories and putting them in arrays for view
        if($directory == null){
            $userId = $this->session->get('auth')['idUser'];
            $query = Sharedfile::findByIdUser($userId);
            foreach ($query as $sharedpath) {
                $physicalPath = $this->persistent->userPath . "../" .  $sharedpath->id_owner . '/' . $sharedpath->path;
                /*// In case owner deletes the shared file, we update the database
                if(!file_exists($physicalPath)){
                    //Delete database entry, maybe a notification could be good
                    $sharedpath->delete();
                }
                else{*/
                    $pathArray = explode('/', $sharedpath->path);
                    if(is_dir($physicalPath)){
                        end($pathArray);
                        array_push($sharedDirectories, array(
                            'realPath' => $sharedpath->id_owner . '/' . $sharedpath->path,
                            'name' => prev($pathArray),
                            'size' => $this->getDirSize($physicalPath)
                            ));
                    }
                    else {
                        $modifyDate = date ("d/m/Y H:i:s.", filemtime($physicalPath));
                        array_push($sharedFiles, array(
                            'realPath' => $sharedpath->id_owner . '/' . $sharedpath->path,
                            'name' => array_pop($pathArray),
                            'size' => $this->getFileSize(filesize($physicalPath)),
                            'modifyDate' => $modifyDate
                        ));
                    }
                //}
            }
        }
        sort($dirArray);
        sort($fileArray);

        if($directory != null)
            $directory = "/" . $directory;

        if($this->persistent->copiedPath == null)
            $this->view->copied = false;
        else
            $this->view->copied = true;

        $this->view->currentDir = $directory;
        $this->view->directories = $dirArray;
        $this->view->files = $fileArray;
        $this->view->shareInfo = null;
        $this->view->sharedFiles = $sharedFiles;
        $this->view->sharedDirectories = $sharedDirectories;
        $this->view->inSharedDirectory = $inSharedDirectory;
    }

    public function viewAction($directory = null) {
        
    }

    public function copyAction($fileSubPath){
        $ds = DIRECTORY_SEPARATOR;
        $user = $this->session->get('auth')['idUser'];
        $fileSubPath = urldecode(str_replace('¤', $ds, $fileSubPath));
        //$this->session->set("copiedPath", $user.$ds.$fileSubPath);
        //error_log($this->session->get('copiedPath'));
        $this->persistent->copiedPath = $user.$ds.$fileSubPath;
        //$this->response->redirect('files/list/');
        //$this->view->disable();
    }

    public function pasteAction(){
        if($this->request->isPost()){
            $destSubPath = $this->request->getPost('currentDir');
            $redirect = $destSubPath;
            if (isset($this->persistent->copiedPath)) {
                $ds = DIRECTORY_SEPARATOR;
                $user = $this->session->get('auth')['idUser'];
                
                $destSubPath = dirname( __FILE__ ) . $ds . '..' . $ds . '..' . $ds . '..' . $ds . "uploadedFiles" .$ds.$user.$ds.$destSubPath.$ds.".";
                $copy = dirname( __FILE__ ) . $ds . '..' . $ds . '..' . $ds . '..' . $ds . "uploadedFiles".$ds.$this->persistent->copiedPath;
                exec("svn cp \"".$copy."\" \"".$destSubPath."\"");
                exec("svn up --accept mine-full");
            }
            $this->response->redirect("files/list".$redirect);
        }
        else {
            $redirect = str_replace($this->url->getBaseUri(), '', $_SERVER['REQUEST_URI']);
            $redirect = str_replace('paste', 'list', $redirect);
            $this->response->redirect($redirect);
        }
    }

    public function getVersionsAction($fileName = null){

        $this->view->disable();


        $fileName = urldecode(str_replace('¤', '\\', $fileName));
        $ds = DIRECTORY_SEPARATOR;
        $storeFolder = "uploadedFiles"; //same as upload
        $user = $this->session->get('auth')['idUser'];
        
        $file = ".." . $ds . ".." . $ds . $storeFolder . $ds;
        // Check if we are in a another directory and changes the path accordingly
        if(is_numeric(current(explode('\\', $fileName))))
            $file .= $fileName;
        else
            $file .= $user . $ds . $fileName;
        error_log("svn log -q \"".addslashes($file)."\" | grep '^r' | cut -f1,5,6 -d' '");
        exec("svn log -q \"".addslashes($file)."\" | grep ^r | cut -f1,5,6 -d\" \"", $output, $returnvalue);
        
        //Create a response instance
        $response = new \Phalcon\Http\Response();

        //Set the content of the response
        $response->setContent(json_encode($output));

        //Return the response
        return $response;

    }

    public function downloadVersionAction($fileName, $version){
        $fileName = str_replace('¤', '\\', $fileName);
        $ds = DIRECTORY_SEPARATOR;
        $storeFolder = "uploadedFiles"; //same as upload
        $user = $this->session->get('auth')['idUser'];
        //Force the download of a file
        $file = ".." . $ds . ".." . $ds . $storeFolder . $ds;
        // Check if we are in a another directory and changes the path accordingly
        if(is_numeric(current(explode('\\', $fileName))))
            $file .= $fileName;
        else
            $file .= $user . $ds . $fileName;
        if(file_exists(realpath($file)))
        {
            if(is_dir(realpath($file))){
                //TODO
                //There doesn't seem to be a simple way to checkout a folder to stdout in svn
                //This could be worked out in the future
                $this->flash->error("You can't download previous versions of a folder at the moment");
                exit;
            }
            else{
                header('Content-Description: File Transfer');
                header('Content-Type: application/octet-stream');
                header('Content-Disposition: attachment; filename='.basename($file));
                header('Expires: 0');
                header('Cache-Control: must-revalidate');
                header('Pragma: public');
                passthru("svn cat -".$version." \"".$file."\"");
                exit;
            }
        }
        else
        {
            $names = explode('\\', $fileName);
            array_pop($names);
            $folderView = implode('\\', $names);
            $this->response->redirect("files/list/".$folderView);
            return;
        }
    }

    public function getFileAction($path){
        $this->view->disable();
        $response = new \Phalcon\Http\Response();

        $ds = DIRECTORY_SEPARATOR;

        $path = str_replace("{}", $ds, $path);

        $userID = $this->session->get('auth')['idUser']; //the user who signed in
        
        $targetPath = dirname( __FILE__ ) . $ds . '..' . $ds . '..' . $ds . '..' . $ds . "uploadedFiles";
        if(is_numeric(next(explode($ds, $path))))
            $targetPath .= $path;
        else
            $targetPath .= $ds . $userID . $path;

        $image = getimagesize($targetPath) ? true : false;
        $ext = pathinfo($targetPath, PATHINFO_EXTENSION);

        if($ext == "php" || $ext == "doc" || $ext == "docx"){
            $data = "Fichier non pris en charge !" ;
        }else if($ext == "pdf"){
            $pdfPath = $targetPath;
            $pdfPath = str_replace($ds, "{}", $pdfPath);
            $data = '<a target="_blank" href="' . $this->url->getBaseUri() .'files/viewPDF/' . $pdfPath . '" style="color: black;"> Visionner le fichier PDF </a>';
        }else if($image || $ext == "png" || $ext == "jpg" || $ext == "jpeg" || $ext == "gif"){
            $imgpath = $targetPath;
            $imgpath = str_replace($ds, "{}", $imgpath);
            $data = '<img style="width: 100%" src="' . $this->url->getBaseUri() .'files/readFile/' . $imgpath . '"/>';
        }else{
            $data = file_get_contents($targetPath, FILE_USE_INCLUDE_PATH);
            if($ext == "html"){
                $data = str_replace("&", "&amp", $data);
                $data = str_replace("<", "&lt", $data);
                $data = str_replace(">", "&gt", $data);
            }else if($ext == "md"){
                $Parsedown = new Parsedown();
                $data = $Parsedown->text($data);
            }
            $data = "<pre>" . $data . "</pre>" ;
        }

        $response->setContent(json_encode($data));
        
        return $response;
    }

    public function viewPDFAction($path){
        // Enlève vue (menu...)
        $this->view->disable();

        $path = str_replace("{}", "/", $path);

        // Affiche le pdf
        $file = $path;
        $filename = 'toto.pdf';
        header('Content-type: application/pdf');
        header('Content-Disposition: inline; filename="' . $filename . '"');
        header('Content-Transfer-Encoding: binary');
        header('Accept-Ranges: bytes');
        @readfile($file);
    }

    public function readFileAction($path){
        $this->view->disable();
        $path = str_replace("{}", DIRECTORY_SEPARATOR, $path);
        readfile($path);
    }

    /**
     * Creation of a new folder by the user.
     *
     * @param string $folderpath
     */
    public function createFolderAction($folderpath = null) {

        if ($this->request->isPost()) {
            // Get the name of the new folder.

            $foldername = urldecode($this->request->getPost("foldername"));
            $foldername = strtr($foldername, array_flip(get_html_translation_table(HTML_ENTITIES, ENT_QUOTES)));
            $foldername = trim($foldername, chr(0xC2).chr(0xA0));
            if ($foldername == null) {
                $this->flash->error("Le nom d'un dossier ne peut être vide.");
                return $this->dispatcher->forward(array(
                    'controller' => 'files',
                    'action' => 'list'
                ));
            }
            // 
            if (file_exists($this->persistent->userPath . "/" . $folderpath . '/'.$foldername)) {
                $this->flash->error("Ce nom existe déja.");
                return $this->dispatcher->forward(array(
                    'controller' => 'files',
                    'action' => 'list'
                ));
            }

            // Get the path where the new folder will be created.
            $pos = strpos(urldecode($_SERVER['REQUEST_URI']),$folderpath);
            if($pos)
                $folderpath = urldecode(substr($_SERVER['REQUEST_URI'], $pos));

            // Check for forbidden characters in the folder name.
            if (preg_match('/[\/:?*<>"|]/', $foldername)) {
                $this->flash->error('Les caractères "/", "\", ":", "?", "*", "<", ">", """, "|" sont interdits.');
            } else {
                mkdir($this->persistent->userPath . "/" . $folderpath . "/" . $foldername);
                $this->flash->success("Le dossier ".$foldername." a été correctement créé");
            }

            exec("svn add \"".$this->persistent->userPath . "/" . $folderpath . "/" . $foldername."\"");
            exec("svn commit -m \"created folder\"");
            exec("svn up --accept mine-full");
            return $this->dispatcher->forward(array(
                'controller' => 'files',
                'action' => 'list'
            ));
        }
    }


    /**
     * Search a file or a folder.
     *
     * @param string $pattern
     */
    public function searchAction($pattern='*') {
        
        if ($this->request->isPost())
            $pattern = $this->request->getPost('pattern');

        // Pattern matching through the folders tree.
        $path = $this->persistent->userPath;
        $paths = array( $path );
        $results = array();
        while( count( $paths ) > 0 )
        {
            $path = array_shift( $paths );
            $paths = array_merge( $paths, glob( $path.'*', GLOB_MARK | GLOB_ONLYDIR | GLOB_NOSORT ) );
            $results = array_merge( $results, glob( $path."*$pattern*", GLOB_NOSORT ) );
        }

        if( count($results) == 0 ) 
        {
            $this->flash->error("Aucun résultat trouvé.");
        }

        // Set the variables for the view.
        $dirArray = array();
        $fileArray = array();
        foreach ( $results as $file )
        {
            $name = substr(strrchr($file, '\\'), 1);
            $localPath = explode($this->persistent->userPath, $file);
            $localPath = $localPath[1];
            $localPath = str_replace('\\', '/', $localPath);
            if( is_dir($file) ){
                $size = $this->getDirSize($file);
                array_push($dirArray, array('name' => $name, 'size' => $size, 'path' => $localPath));
            } else {
                $localPath = explode('/', $localPath);
                array_pop($localPath);
                $localPath = '/' . implode('/', $localPath);
                $size = $this->getFileSize(filesize($file));
                $modifyDate = date ("d/m/Y H:i:s.", filemtime($file));
                array_push($fileArray, array('name' => $name, 'size' => $size, 'modifyDate' => $modifyDate, 'path' => $localPath));
            }
        }

        $this->view->files = $fileArray;
        $this->view->directories = $dirArray;
    }

    

    public function shareAction() {
        if (!$this->request->isPost()) {
            return $this->dispatcher->forward(array(
                "controller" => "Files",
                "action" => "list"
            ));
        }

        $userId = $this->session->get('auth')['idUser'];
        $email = $this->request->getPost("userMail");

        if($userShare = User::findFirstByemail($email)) {
            if($userId == $userShare->idUser) {
                $this->response->setJsonContent(array('message' => "Désolé, il n'est pas possible de partager avec soit-même..."));
                return $this->response;
            }

            $sharedPaths = $this->request->getPost("paths");
            if($sharedPaths) {  
                foreach ($sharedPaths as $path) {
                    if($sharedFile = Sharedfile::findFirst(array("path = ?0 and id_user = ?1", "bind" => array($path, $userShare->idUser)))) {
                        $this->response->setJsonContent(array('message' => 'Fichier(s)/Dossier(s) déjà partagé(s) avec cet utilisateur'));
                    } else {
                        $pathTmp = $path;
                        $pathTrim = rtrim(ltrim($pathTmp, '/'), '/');
                        $pathArray = explode('/', $pathTrim);
                        $elemShared = array_pop($pathArray);
                        if($elemShared != "..") {
                            $sharedFile = new Sharedfile();
                            $sharedFile->id_user = $userShare->idUser;
                            $sharedFile->path = $path;
                            $sharedFile->id_owner = $userId;
                            $this->response->setJsonContent(array('message' => 'Partage réussi !'));
                        
                            if(!$sharedFile->save()) {
                                $this->response->setJsonContent(array('message' => 'Erreur lors du partage'));
                                return $this->response;
                            } else {
                                $notif = new Notification();
                                $notif->message = $this->session->get('auth')['login'] . " a partage " . $elemShared . " avec vous.";
                                $notif->unread = true;
                                $notif->id_SharedFile = $sharedFile->idShared_File;
                                if(!$notif->save()) {
                                    $this->response->setJsonContent(array('message' => "TEST"));
                                    foreach ($notif->getMessages() as $message) { 
                                        $this->flash->error($message);
                                    }
                                }
                            }
                        } else {
                            $this->response->setJsonContent(array('message' => 'Fichier ou dossier invalide.'));
                        }
                    }
                }
            } else {
                $this->response->setJsonContent(array('message' => "Vous n'avez rien séléctionné !"));
                return $this->response;
            }
        } else {
            $this->response->setJsonContent(array('message' => 'Mail invalide'));
            return $this->response;
        }

        return $this->response;
    }
}
