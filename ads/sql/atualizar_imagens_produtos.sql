-- Rode isso SÓ se você já tem o banco populado (com caminho antigo
-- "img/...") e não quer reimportar o banco_completo.sql inteiro
-- (que reseta os dados). Move as imagens para a pasta nova assets/img/.

USE barbearia_adrian_souza;

UPDATE produtos SET imagem = REPLACE(imagem, 'img/', 'assets/img/')
WHERE imagem LIKE 'img/%';

-- Se ainda sobrar algum caminho antigo em .png (versão bem anterior),
-- troca pra .jpg também:
UPDATE produtos SET imagem = REPLACE(imagem, '.png', '.jpg')
WHERE imagem LIKE '%.png';
