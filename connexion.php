<?php
   
require_once 'web/session.php';
use Web\SessionManager;
SessionManager::start();
require_once 'web/strEditor.php';


if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["reset_password"])) {
    $newPassword = $_POST["newPassword"];
    $confirmPassword = $_POST["confirmPassword"];
    $userId = $_POST["userId"];
    
    if ($newPassword === $confirmPassword && strlen($newPassword) >= 6) {
        try {
            $conn = require("web/dbconnection.php");
            require("web/dbmanager.php");
            $manager = new DatabaseManager(connection: $conn);
            
            // Mettre à jour le mot de passe et marquer comme réinitialisé
            $manager->update("UTILISATEUR", 
                ["MDP" => password_hash($newPassword, PASSWORD_DEFAULT), "reset_required" => 0],
                ["idUser" => $userId]
            );
            
            // Enregistrer l'action
            $dataAction = [
                'idUser' => $userId,
                'DateA' => date('Y-m-d H:i:s'),
                'typeAction' => 'Réinitialisation de mot de passe',
                'Concerne' => ''
            ];
            $manager->insert("ACTIONS", $dataAction);
            
            echo json_encode(['success' => true]);
            exit();
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit();
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Mots de passe invalides']);
        exit();
    }
}

    $nameErr = $mdpErr = $loginErr= "";
    $mdpValid = false;
    $nameValid = false;
    $loginValid = false;
    $chemain = "";
    $showResetModal = false;
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $username = test_input($_POST["username"]);
        $password = test_input($_POST["password"]);
        if (empty($username)) {
            $nameErr = "Identifiant requis";
    
        }
        if (empty($password)) {
            $mdpErr = "Mot de passe requis";
        }
        try {
        $conn =  require("web/dbconnection.php");
        require ("web/dbmanager.php");
        $manager = new DatabaseManager(connection: $conn);
        $utilisateur = $manager->select("UTILISATEUR",['*'],['identifiant' =>$username]);
        $utilisateur = $utilisateur[0];
        echo "<pre>";
        print_r($utilisateu);
        echo "</pre>";

        } catch (PDOException $e) {}
        if (!empty($utilisateur)) {
            $nameValid = $username == $utilisateur['identifiant'] ;
            $mdpValid =   password_verify($password,$utilisateur['MDP']);
            $mdpValid = $password == $utilisateur['MDP']; 
            $loginValid = $nameValid && $mdpValid;
        } 
        if ($loginValid){ 
            if ($utilisateur['reset_required'] == 1) {
                $showResetModal = true;
            } else {
            // Démarrer la session utilisateur
            switch ($utilisateur['idRole']) {
                case 1:
                    $utilisateur['idRole'] = 'Admin';
                    break;
                case 2:
                    $utilisateur['idRole'] = 'Médecin';
                    break;
                case 3:
                    $utilisateur['idRole'] = 'Infirmière';
                    break;
                default:
                    $utilisateur['idRole'] = 'Error';
                    break;
            }
            date_default_timezone_set('Europe/Paris');
            $dataAction = [
                'idUser' => $utilisateur['idUser'],
                'DateA' => date('Y-m-d H:i:s'), // Équivalent de NOW() en PHP
                'typeAction' => 'Connexion',
                'Concerne' => ''
            ];
            $actionId = $manager->insert("ACTIONS", $dataAction);
            SessionManager::set('user_id', $utilisateur['idUser']);
            SessionManager::set('nom', $utilisateur['nom']);
            SessionManager::set('prenom', $utilisateur['prenom']);
            SessionManager::set('role', $utilisateur['idRole']);

            header("Location: web/accueil.php");
            exit();
            }
        }

        else {$loginErr = "Connexion échouée. 
            </br> Vérifiez vos informations d'identification et réessayez. 
            </br> Si vous avez oublié votre mot de passe, veuillez contacter l'administrateur.";}
        
    }
    
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>R.O.S.A</title>
    <link rel="icon" type="image/x-icon" href="../data/images/favicon.ico">

    <link rel="stylesheet" href="web\style\styles.css">
    
    <link href="web\style\bootstrap.min.css" rel="stylesheet">
    
    <title>Page de Connexion</title>
</head>
<body class="d-flex flex-column align-items-center justify-content-around bg-white">

    
   <div>
        <img src="data\images\Logo_ROSA.png" alt="logo_ROSA">
        <img src="data\images\ROSA.png" alt="ROSA">
   </div>
    <!-- carré de connexion -->
   <div>
        <form action="<?php if ($loginValid){ echo "web/accueil.php";}?>" method="POST" autocomplete="off" class="d-flex flex-column align-items-start justify-content-around border border-3 rounded-4 adelle p-5" style="width: 550px">
            <h1 class="adelle-bold font_meadow">Connexion</h1>
            <div class="d-flex flex-column align-items-start justify-content-around p-2 w-100">
                <h4 class=" font_meadow">Identifiant</h4>
                <input type="text" class="form-control w-100" id="username" name="username" placeholder="Entrer votre identifiant">
                <span class="error text-danger">* <?php echo $nameErr;?></span>
            </div>
            <div class="d-flex flex-column align-items-start justify-content-around p-2 w-100">
                <h4 class=" font_meadow">Mot de passe</h4>
                <input type="password" class="form-control w-100" id="password" name="password" placeholder="Entrer mot de passe">
                <span class="error text-danger">* <?php echo $mdpErr;?></span>
            </div>
            <span class="error text-danger"><?php echo $loginErr;?></span>
            <input class="btn btn-outline-success align-self-center" type="submit" value="Se connecter">
        </form>
   </div>
   <?php if ($showResetModal): ?>
    <div class="modal fade show d-block" id="resetPasswordModal" tabindex="-1" aria-labelledby="resetPasswordModalLabel" aria-hidden="false" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header background_meadow text-white">
                    <h5 class="modal-title" id="resetPasswordModalLabel">
                        Réinitialisation obligatoire du mot de passe
                    </h5>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-4">
                        <i class="fas fa-key text-primary" style="font-size: 3rem;"></i>
                        <p class="mt-3 text-muted">Bonjour <?php echo $utilisateur['prenom']; ?>, vous devez changer votre mot de passe temporaire.</p>
                    </div>
                    
                    <form id="resetPasswordForm">
                        <input type="hidden" id="userId" value="<?php echo $utilisateur['idUser']; ?>">
                        
                        <div class="mb-3">
                            <label for="newPassword" class="form-label">Nouveau mot de passe</label>
                            <input type="password" class="form-control" id="newPassword" name="newPassword" required placeholder="Votre nouveau mot de passe" minlength="6">
                            <div class="invalid-feedback">
                                Le mot de passe doit contenir au moins 6 caractères.
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="confirmPassword" class="form-label">Confirmer le mot de passe</label>
                            <input type="password" class="form-control" id="confirmPassword" name="confirmPassword" required placeholder="Confirmez votre mot de passe">
                            <div class="invalid-feedback">
                                Les mots de passe ne correspondent pas.
                            </div>
                        </div>

                        <div class="alert alert-info" role="alert">
                            <small>Votre mot de passe doit contenir au moins 6 caractères.</small>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="submit" form="resetPasswordForm" class="btn btnROSA">
                        Enregistrer le mot de passe
                    </button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script src="web/style/bootstrap.bundle.min.js"></script>
    <script>
        <?php if ($showResetModal): ?>
        document.getElementById('resetPasswordForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const newPassword = document.getElementById('newPassword');
            const confirmPassword = document.getElementById('confirmPassword');
            const userId = document.getElementById('userId').value;
            let isValid = true;
            
            // Validation du nouveau mot de passe
            if (newPassword.value.length < 6) {
                newPassword.classList.add('is-invalid');
                isValid = false;
            } else {
                newPassword.classList.remove('is-invalid');
                newPassword.classList.add('is-valid');
            }
            
            // Validation de la confirmation
            if (confirmPassword.value !== newPassword.value) {
                confirmPassword.classList.add('is-invalid');
                isValid = false;
            } else {
                confirmPassword.classList.remove('is-invalid');
                confirmPassword.classList.add('is-valid');
            }
            
            if (!isValid) return;
            
            // Envoi AJAX
            const formData = new FormData();
            formData.append('reset_password', '1');
            formData.append('newPassword', newPassword.value);
            formData.append('confirmPassword', confirmPassword.value);
            formData.append('userId', userId);
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Rediriger vers l'accueil après succès
                    window.location.href = 'web/accueil.php';
                } else {
                    alert('Erreur: ' + (data.error || 'Erreur inconnue'));
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                alert('Erreur de connexion');
            });
        });

        // Réinitialiser la validation quand on tape dans les champs
        document.getElementById('newPassword').addEventListener('input', function() {
            this.classList.remove('is-invalid', 'is-valid');
        });
        
        document.getElementById('confirmPassword').addEventListener('input', function() {
            this.classList.remove('is-invalid', 'is-valid');
        });
        <?php endif; ?>
    </script>
</body>
</html>