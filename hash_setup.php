<?php
/**
 * hash_setup.php
 * File ini berisi fungsi-fungsi untuk menangani hashing password yang aman.
 */

/**
 * Membuat hash password baru.
 *
 * @param string $password Password plain text.
 * @return string Hashed password.
 */
function hashPassword($password) {
    // Menggunakan PASSWORD_DEFAULT (BCRYPT) untuk keamanan terbaik.
    return password_hash($password, PASSWORD_DEFAULT);
}

/**
 * Memverifikasi password saat login.
 *
 * @param string $password Password plain text yang diinput user.
 * @param string $hashedPassword Hashed password dari database.
 * @return bool True jika cocok, False jika tidak.
 */
function verifyPassword($password, $hashedPassword) {
    return password_verify($password, $hashedPassword);
}
?>