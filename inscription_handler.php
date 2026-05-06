<?php
// Traitement du formulaire d'inscription
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Récupérer les données du formulaire
    $nom = trim($_POST['nom']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Vérifier les champs obligatoires
    if (empty($nom) || empty($email) || empty($password) || empty($confirm_password)) {
        die('Tous les champs sont obligatoires.');
    }

    // Vérifier la correspondance des mots de passe
    if ($password !== $confirm_password) {
        die('Les mots de passe ne correspondent pas.');
    }

    // Hachage du mot de passe
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Connexion à la base de données
    require_once 'includes/db.php';

    // Insérer l'utilisateur dans la base de données
    $stmt = $conn->prepare("INSERT INTO users (nom, email, password) VALUES (?, ?, ?)");
    $stmt->bind_param('sss', $nom, $email, $hashed_password);

    if ($stmt->execute()) {
        echo 'Inscription réussie. Vous pouvez maintenant vous connecter.';
    } else {
        echo 'Erreur lors de l\'inscription : ' . $stmt->error;
    }

    $stmt->close();
    $conn->close();
}
?>