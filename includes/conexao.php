<?php
/**
 * ATENÇÃO: este é um MODELO.
 * Cole aqui os dados reais que já estavam no seu conexao.php original
 * (host, usuário, senha e nome do banco do MySQL/XAMPP).
 */

$host  = 'localhost';
$usuario_bd = 'root';
$senha_bd   = '';
$banco = 'barbearia_adrian_souza';

$conexao = new mysqli($host, $usuario_bd, $senha_bd, $banco);

if ($conexao->connect_error) {
    die('Erro de conexão com o banco: ' . $conexao->connect_error);
}

$conexao->set_charset('utf8mb4');

// Usado no rodapé/loja do site público (botão "Comprar pelo WhatsApp")
// Cole aqui o número real que já estava definido no seu projeto.
if (!defined('WHATSAPP_VENDAS')) {
    define('WHATSAPP_VENDAS', '5544997306220');
}
