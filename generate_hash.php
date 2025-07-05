<?php
    $plaintext_password = 'mahasiswa';
    $hashed_password = password_hash($plaintext_password, PASSWORD_DEFAULT);

    echo "Password Plaintext: " .$plaintext_password . "\n";
    echo "Password Hash: " .$hashed_password . "\n";
?>
