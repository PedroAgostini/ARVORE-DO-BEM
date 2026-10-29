<?php
// Opcional. Sem este arquivo, o formulário já funciona com os valores abaixo.
// Para mudar algo, copie para config.php (na mesma pasta) e edite.

return [
    // Quem recebe o aviso de cada cadastro
    'destino' => 'contato@fundacaoedmilson.org.br',

    // Remetente dos avisos, enviados pelo e-mail interno da Hostinger
    'remetente' => 'no-reply@arvoredobem.fundacaoedmilson.org.br',
    'remetente_nome' => 'Árvore do Bem',

    'dominio' => 'arvoredobem.fundacaoedmilson.org.br',

    // Qualquer texto aleatório: protege os IPs guardados no limite de envios
    'sal' => 'troque-este-texto',
];
