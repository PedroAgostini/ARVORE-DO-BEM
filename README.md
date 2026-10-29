# Árvore do Bem · Fundação Edmílson

Página da campanha de Natal 2026 da Fundação Edmílson. Padrinhos e empresas apadrinham crianças atendidas pela Fundação e entregam um kit com roupa, sapato, brinquedo e item de higiene, de 03/11 a 30/11/2026.

**Produção:** https://arvoredobem.fundacaoedmilson.org.br

## Estrutura

| Caminho | O que é |
|---|---|
| `index.html` | A página inteira: HTML, CSS e JavaScript num arquivo só, sem build |
| `404.html` | Página de erro personalizada |
| `assets/` | Cartoons, logos, marca d'água (`catavento.png`) e imagem de compartilhamento (`og-image.jpg`) |
| `.htaccess` | HTTPS, domínio canônico, página 404, cabeçalhos de segurança, compressão e cache (Apache) |
| `favicon.*`, `icon-*.png`, `apple-touch-icon.png`, `site.webmanifest` | Ícones e manifesto |
| `robots.txt`, `sitemap.xml`, `llms.txt`, `humans.txt`, `.well-known/security.txt` | Arquivos para buscadores, assistentes de IA e contato de segurança |

## Formulário

O formulário tem 3 passos (dados, contato e crianças). No último, o padrinho escolhe quantas crianças vai ajudar e, se já tiver o cartão, informa o código e o nome de cada uma (opcional, até 10). Ao confirmar:

1. manda os dados para o `enviar.php`, no próprio site, que:
   - salva o cadastro na planilha `privado/cadastros.csv` (abre direto no Excel ou no Google Planilhas);
   - avisa `contato@fundacaoedmilson.org.br` por e-mail, enviando pelo e-mail interno da Hostinger (sem senha nem serviço externo);
2. mostra a mensagem de obrigado com o botão "Enviar mensagem no WhatsApp", que abre o WhatsApp da campanha (+55 11 91574-5343) com a mensagem pronta para combinar a entrega.

A planilha é a garantia: se o e-mail falhar, o cadastro continua salvo. Falhas de envio ficam registradas em `privado/erros.log`.

Proteções contra spam, sem captcha, conferidas no navegador e de novo no servidor: campo-isca, tempo mínimo de preenchimento, filtro de links e palavras proibidas nos nomes, limite de 8 cadastros por hora por IP, aceite só de envios vindos do próprio site e bloqueio de reenvio idêntico por 10 minutos.

### Depois de publicar

1. Faça um cadastro de teste no site e confira se a linha apareceu em `privado/cadastros.csv` e se o e-mail chegou em `contato@`.
2. Se o e-mail cair no spam, marque como "Não é spam". No Gmail, crie também um filtro para o remetente `no-reply@arvoredobem.fundacaoedmilson.org.br` com a opção "Nunca enviar para spam".
3. Se o e-mail não chegar nem no spam, crie a caixa `no-reply@arvoredobem.fundacaoedmilson.org.br` no painel de e-mails da Hostinger (algumas hospedagens só enviam por um remetente que existe) ou veja o motivo em `privado/erros.log`.

Nada precisa ser configurado para funcionar. Para trocar o destinatário ou o remetente, copie `privado/config.exemplo.php` para `privado/config.php` e edite.

O `config.php` e os arquivos de dados da pasta `privado` estão no `.gitignore`: os cadastros nunca vão para o GitHub. Para baixar a planilha, use o Gerenciador de Arquivos da Hostinger; a pasta é bloqueada para acesso pela web.

## Publicação

Suba o conteúdo desta pasta na raiz do domínio, num servidor Apache ou LiteSpeed com PHP 8.1 ou mais novo, `mod_rewrite`, `mod_headers`, `mod_expires` e `mod_deflate` (a Hostinger atende a tudo isso).

- As imagens usam `?v=N` no endereço. Ao trocar uma imagem, aumente o número no `index.html` para furar o cache.
- O `.htaccess` define uma Content-Security-Policy que libera só as fontes do Google; o formulário envia apenas para o próprio site. Ao adicionar Analytics, Pixel ou outro script externo, inclua o domínio nela.
