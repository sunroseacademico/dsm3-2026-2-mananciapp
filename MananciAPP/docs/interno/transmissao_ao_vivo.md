# Transmissão ao vivo

Como a live funciona no MananciAPP e o passo a passo para transmitir um
culto. Substitui o antigo `plano_transmissao_ao_vivo.md` (o plano de antes
da implementação, que está no git).

## 1. Como funciona

A igreja transmite pelo **YouTube Live** (grátis). O app só **embute o
player** do YouTube e tem **chat próprio**: o visitante não sai do site e
não vai para o youtube.com.

| Parte | Como é |
|---|---|
| Vídeo | `<iframe>` do YouTube. O admin cola o link (ou só o ID) da live; `transmissao_extrair_youtube_id()` reconhece os formatos comuns. |
| Quantas lives | **Uma por vez.** Cadastrar uma nova apaga a anterior e o chat dela. |
| Status | `AGENDADA` → `AO_VIVO` → `ENCERRADA`, marcados pelo admin (com trava para dois admins não mudarem ao mesmo tempo). |
| Quem assiste (`idtr_audiencia`) | **Todos** (inclusive sem login), **Somente fiéis** ou **Apenas admins**. Quem não pode vê uma tela de "restrito" (`transmissao_pode_assistir()`, que também confere se a sessão ainda vale). |
| Chat | Todos que podem assistir leem; só conta aprovada comenta. Atualiza a cada ~5 s (pausa com a aba escondida). Na página pública, o nome aparece curto ("Maria L."). A lista vem de um cache de 2 s igual para todos (centenas de pessoas sem pesar no banco); mandar mensagem e moderar no admin apagam o cache, e o "Ao vivo" do menu acende em até 15 s (na hora quando o admin inicia). Ver [capacidade.md](capacidade.md). |
| Moderação | O admin oculta (reversível) ou exclui mensagens e bloqueia um fiel de comentar em **qualquer** live por 1 h, 1 dia, 7 dias, 30 dias ou para sempre. A tela atualiza sozinha, sem reiniciar o vídeo. |
| Avisos | Notificação no app + push quando a live é criada, fica ao vivo e é encerrada, só para quem pode assistir. |
| "Ao Vivo" no menu | O ícone só pisca quando existe uma live `AO_VIVO`. |
| Replay | Depois de encerrada, a página continua tocando o mesmo vídeo (o YouTube mantém o ID) com o chat. Quando outra live é cadastrada, a anterior some do app (ver "Lives anteriores" em [pontos_gerais.md](pontos_gerais.md), seção 5.3). |

### Onde está no código

| Arquivo | O quê |
|---|---|
| `ao-vivo.php` (raiz) | Página pública |
| `php/fiel/ao_vivo.php` | A mesma tela dentro da área do fiel |
| `php/includes/transmissao_widget.php` e `transmissao_script.php` | Vídeo + chat (HTML e JS), usados pelas duas páginas |
| `ajax_transmissao_comentarios.php`, `ajax_transmissao_status.php` (raiz) | Leitura pública do chat e status para o pisca-pisca do menu |
| `php/fiel/ajax_transmissao_comentar.php` | Enviar mensagem |
| `php/admin/admin_transmissao.php`, `ajax_admin_transmissao_comentarios.php` | Cadastrar, mudar status e moderar |
| `php/conexao.php`, funções `transmissao_*` | Regras (público, bloqueio, avisos, live atual) |
| `js/live-indicator.js`, `css/transmissao.css` | Indicador do menu e estilo da tela |

Tabelas: `id_transmissao`, `id_transmissao_comentario` e
`id_fiel_bloqueio_chat` (ver `database/bancosupabase.md`). A CSP do
`.htaccess` libera o domínio do YouTube para o player.

## 2. Contas e equipamento (já feito)

- ✅ Canal do YouTube **SunRose** (`@The-Sun-Rose`) com transmissão ao vivo
  liberada (pedido em 21/09/2026).
- **OBS Studio** ([obsproject.com](https://obsproject.com), grátis) no
  computador ligado à câmera e à mesa de som. Em Configurações → Transmissão
  → Serviço: YouTube, colar a chave de transmissão do YouTube Studio (só uma
  vez; depois é reaproveitada).

## 3. Passo a passo de um culto

1. **Antes (uma vez):** fazer um ensaio com uma transmissão **"Não
   listada"** no YouTube para conferir áudio e vídeo.
2. **YouTube Studio** → Criar → Transmitir ao vivo. Visibilidade **"Não
   listada"** (só quem tem o link vê; o app toca normalmente). Copiar o link.
3. **App → Admin → Transmissão:** colar o link, dar um título e escolher
   quem pode assistir. Os fiéis recebem o aviso de que a live foi agendada.
4. **No horário:** iniciar no OBS → conferir no YouTube Studio → no admin,
   botão **"Ao Vivo"** (os fiéis recebem o push).
5. **Durante:** moderar o chat pela própria tela do admin.
6. **No fim:** parar no OBS e, no admin, botão **"Encerrar"**.

## 4. Pendências

- [ ] **Licença de música** (ex.: CCLI Streaming License) se o louvor usa
  músicas de terceiros. Sem ela o YouTube pode derrubar ou silenciar a
  live (Content ID). É decisão da liderança, não do código.
- [ ] Aba "Lives anteriores" (proposta em
  [pontos_gerais.md](pontos_gerais.md), seção 5.3).
- [ ] Decidir se as lives "Somente fiéis" exigem assinatura no modo "Só
  assinantes" (seção 5.4 do mesmo arquivo).

## 5. Por que o YouTube

| Opção | Custo | Observação |
|---|---|---|
| **YouTube Live** (escolhido) | Grátis | Zero infraestrutura de vídeo; a marca do YouTube aparece no player |
| Cloudflare Stream / Mux | Pago por minuto | Sem marca de terceiro |
| AWS IVS | Pago | Baixa latência, mais configuração |
| Servidor próprio (nginx-rtmp) | Tempo e risco | Não recomendado: pode cair no meio do culto |

Trocar de serviço no futuro não mexe no chat nem na moderação (são do
app): muda só o player e a forma de transmitir.
