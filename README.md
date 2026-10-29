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

1. abre o WhatsApp da campanha (+55 11 91574-5343) com a mensagem pronta;
2. envia uma cópia por e-mail para `contato@fundacaoedmilson.org.br` via [FormSubmit](https://formsubmit.co).

O FormSubmit precisa ser ativado uma vez pelo link que ele envia ao e-mail de destino no primeiro envio.

Proteções contra spam, sem captcha: campo-isca, tempo mínimo de preenchimento, filtro de links no nome e na empresa, bloqueio de reenvio idêntico por 10 minutos e lista de palavras proibidas no FormSubmit.

## Publicação

Suba o conteúdo desta pasta na raiz do domínio, num servidor Apache com `mod_rewrite`, `mod_headers`, `mod_expires` e `mod_deflate`.

- As imagens usam `?v=N` no endereço. Ao trocar uma imagem, aumente o número no `index.html` para furar o cache.
- O `.htaccess` define uma Content-Security-Policy que libera só Google Fonts e FormSubmit. Ao adicionar Analytics, Pixel ou outro script externo, inclua o domínio nela.
