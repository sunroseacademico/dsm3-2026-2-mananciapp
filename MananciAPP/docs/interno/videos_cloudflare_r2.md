# Vídeos e imagens no Cloudflare R2

> Por que os arquivos do app (vídeos das aulas e capas dos cursos) ficam no
> Cloudflare R2, como ficou a migração e como recriar a configuração em
> outro projeto. Como o código funciona: seção 5 de
> [DEPLOY_RENDER.md](../../DEPLOY_RENDER.md). O banco continua no Supabase.
>
> Junta os antigos `custos_armazenamento_videos.md` e
> `cronograma_cloudflare_r2.md` (o passo a passo detalhado, com código, está
> no histórico do git, commit `916382d` e anteriores).
> Preços em dólar, levantados em out/2026: conferir em
> [supabase.com/pricing](https://supabase.com/pricing) e
> [developers.cloudflare.com/r2/pricing](https://developers.cloudflare.com/r2/pricing/).
> Conversões com US$ 1 ≈ R$ 5,50 + ~3,5% de IOF.

## 1. Situação (concluída em 15/10/2026)

| Etapa | Situação |
|---|---|
| Conta, bucket, token, CORS e alerta de cobrança na Cloudflare | ✅ |
| Upload direto do navegador para o R2, reprodução com URL assinada | ✅ (`cf723a1`) |
| Exclusão automática do arquivo ao excluir aula/curso ou trocar vídeo/capa | ✅ (`cf723a1`, `3257e41`) |
| Vídeos antigos | ✅ Não foram migrados: as aulas eram de teste e foram reenviadas pelo admin |
| Supabase Storage desligado (código, arquivos, tabela de cache, variáveis, chave secreta revogada) | ✅ (`916382d`) |
| Capas dos cursos no R2 (antes sumiam a cada deploy no Render) | ✅ (`3257e41`) |
| Compressão dos vídeos antes de enviar | ⏳ Orientação para a igreja (seção 5) |

Decisões que mudaram em relação ao plano original:

- **Um bucket só, `mananciapp-videos-homolog`.** O "produção" de hoje é o
  próprio Render (`mananciapp-render-homolog.onrender.com`), e o ambiente
  local usa o mesmo banco. Como `IDCT_URL`/`IDC_CAPA_URL` são
  compartilhados, os dois precisam do mesmo bucket. Não há plano de trocar
  tão cedo.
- **Chave liga/desliga temporária.** O primeiro deploy (`cf723a1`) só
  enviava pro R2 com `VIDEO_STORAGE=r2`, com o Supabase de reserva, para não
  arriscar a demonstração ao cliente. Saiu junto com o Supabase.
- **Sem migração com rclone nem espera de 15 dias:** os vídeos antigos não
  importavam.

Pontos ainda em aberto (cartão da conta, lixeira para vídeo apagado, lives
anteriores): seção 5 de [pontos_gerais.md](pontos_gerais.md).

## 2. Por que o R2

### O problema

O Supabase fazia duas coisas: o **banco** (que continua lá) e o **storage
dos vídeos**. Vídeo é pesado para guardar e, principalmente, para
**entregar**: cada fiel assistindo baixa o arquivo, e esse tráfego de saída
(egress) é cobrado no Supabase.

### Planos e limites

| | Supabase Free | Supabase Pro (US$ 25/mês) | Cloudflare R2 |
|---|---|---|---|
| Armazenamento | 1 GB | 100 GB (depois ~US$ 0,021/GB) | 10 GB grátis, depois US$ 0,015/GB/mês |
| Tamanho por arquivo | 50 MB | configurável | ~5 GB por envio simples |
| Tráfego de saída | 5 GB/mês | 250 GB/mês (depois **US$ 0,09/GB**) | **ilimitado e grátis** |
| Operações | — | — | 1 milhão de envios e 10 milhões de leituras grátis/mês |
| Banco | 500 MB, pausa após 7 dias sem uso, sem backup | 8 GB, sem pausa, backup diário | — |

O R2 exige cartão cadastrado mesmo na faixa grátis e não tem teto que
bloqueie a cobrança (só alerta).

### Simulações (aula de ~300 MB, cada fiel baixa o vídeo quase inteiro)

| Cenário | Guardado | Tráfego/mês | Supabase Pro | Supabase Free + R2 | Supabase Pro + R2 |
|---|---|---|---|---|---|
| Início: 20 aulas, 100 fiéis × 8 aulas | 6 GB | 240 GB | US$ 25 (~R$ 142) | **US$ 0** | US$ 25 |
| Crescendo: 60 aulas, 300 fiéis × 10 aulas | 18 GB | 900 GB | US$ 83,50 (~R$ 475) | **~US$ 0,12** | US$ 25,12 |
| Grande: 150 aulas, 1.000 fiéis × 10 aulas | 45 GB | 3.000 GB | US$ 268 (~R$ 1.525) | **~US$ 0,53** | US$ 25,53 |

No Supabase o custo cresce com **quantas pessoas assistem**; no R2, só com
**quantos GB estão guardados**, e bem devagar. (Supabase Free sozinho não
comporta nenhum cenário: 50 MB por arquivo e 1 GB no total.)

### Recomendação

- **Agora:** Supabase Free (banco) + R2 (arquivos). Custo praticamente zero.
- **Com assinantes pagando:** Supabase Pro (banco) + R2. Os US$ 25/mês
  passam a valer pelo **banco** (backup diário, sem pausa), com custo fixo
  (~R$ 145/mês) mesmo com mil fiéis.

### Alternativas avaliadas

- **Cloudflare Stream:** player pronto e qualidade adaptável (bom no 4G),
  mas US$ 5/1.000 min guardados + US$ 1/1.000 min assistidos — cresce com a
  audiência (300 fiéis × 10 aulas × 60 min ≈ US$ 180/mês).
- **Bunny Stream:** parecido, mais barato, cobrado por GB entregue
  (~US$ 0,005–0,01/GB).

## 3. Como recriar a configuração (outro projeto ou outra conta)

1. **Conta:** [dash.cloudflare.com](https://dash.cloudflare.com) →
   Armazenamento e banco de dados → **Armazenamento de objetos R2** →
   "Adicionar assinatura do R2" (pede cartão; só cobra acima do grátis).
   Hoje: conta `sunrosefatec@...`, Account ID
   `d4fe90bbe77224bdd70445f326458180`.
2. **Bucket:** "Criar bucket", localização **Automática**, classe
   **Standard**. Em Configurações, deixar **desativados** o "URL de
   desenvolvimento público" (r2.dev) e os "Custom Domains": o bucket é
   privado, o app só entrega URLs assinadas.
3. **Política de CORS** (Configurações → Política de CORS). A origem inclui
   a porta:

```json
[
  {
    "AllowedOrigins": [
      "http://localhost",
      "http://localhost:8000",
      "https://mananciapp-render-homolog.onrender.com"
    ],
    "AllowedMethods": ["PUT", "GET", "HEAD"],
    "AllowedHeaders": ["content-type"],
    "ExposeHeaders": ["ETag"],
    "MaxAgeSeconds": 3600
  }
]
```

4. **Token:** página do R2 → Gerenciar tokens de API → "Criar token de API
   da conta": **Leitura/gravação para objeto**, **somente no bucket do
   app**, TTL **Sempre**. Copiar o ID da chave de acesso e a chave secreta
   (ela só aparece uma vez).
5. **Variáveis** no `.env` local e no Render (Environment): `R2_ACCOUNT_ID`,
   `R2_ACCESS_KEY_ID`, `R2_SECRET_ACCESS_KEY`, `R2_BUCKET`.
6. **Alerta de cobrança:** Notificações → Adicionar → "Faturamento baseado
   em uso" → **R2 Storage**, limite **8 GB** (o grátis é 10 GB).
7. **Testar** com o F12 aberto: enviar uma aula curta, assistir, excluir e
   conferir no bucket que o arquivo entrou e saiu. Sem erro de CSP/CORS no
   Console.

Teste local sem o Apache do XAMPP: `C:\xampp\php\php.exe -S localhost:8000
-t .` dentro de `MananciAPP/` (por isso `localhost:8000` está no CORS).
Nesse modo o `.htaccess` não é lido, então a CSP só é testada no Render.

## 4. Organização do bucket

| Pasta | O que guarda | Valor no banco |
|---|---|---|
| `aulas/` | Vídeos das aulas | `ID_CONTENT.IDCT_URL = 'r2:aulas/<timestamp>_<nome>.<ext>'` |
| `capas/` | Capas dos cursos | `ID_CURSO.IDC_CAPA_URL = 'r2:capas/<timestamp>_<nome>.<ext>'` |

Fotos de perfil não usam o R2: ficam no próprio banco (`id_fiel_foto`). As
4 capas de exemplo ficam no git, em `assets/capas_cursos/`.

## 5. Compressão dos vídeos (orientação, sem código)

Vídeo de celular/câmera sai com 1–4 GB por hora de aula. Exportando em
**720p, H.264, ~1,5–2,5 Mbps** (HandBrake, gratuito, preset "Fast 720p30")
cai para ~300–500 MB, sem perda visível em pregação ou aula: cabe ~5× mais
aulas nos 10 GB grátis, o envio fica rápido e o fiel gasta bem menos dados
no 4G. Texto para a igreja: seção 11 de
[pontos_para_alinhar.md](../para_o_cliente/pontos_para_alinhar.md).
