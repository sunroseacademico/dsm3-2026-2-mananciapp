# Teste de carga

Mede quantas pessoas o site aguenta ao mesmo tempo antes de ficar lento ou
dar erro. Meta do lançamento: **300 simultâneos** (70% navegando e vendo
aula, 30% na live).

**Sempre na homologação, nunca na produção.** O teste cria sessões, marca
presença e pede o chat da live centenas de vezes por segundo.

## Instalar o k6 (uma vez)

Windows: `winget install k6 --source winget` (ou baixar em
https://grafana.com/docs/k6/latest/set-up/install-k6/). Conferir com
`k6 version`.

## Rodar

De dentro de `MananciAPP/`:

```
# 1. Conferir o script (5 pessoas, 1 minuto)
k6 run -e BASE=https://mananciapp-render-homolog.onrender.com/ -e CENARIO=fumaca -e CONTAS=contas.csv tests/carga/carga.js

# 2. Subindo aos poucos até o pico (11 minutos): mostra onde começa a piorar
k6 run -e BASE=https://mananciapp-render-homolog.onrender.com/ -e CONTAS=contas.csv tests/carga/carga.js

# 3. O culto: todo mundo chegando em 1 minuto, 70% na live (12 minutos).
#    Deixe uma transmissão AO VIVO na homologação antes.
k6 run -e BASE=https://mananciapp-render-homolog.onrender.com/ -e CENARIO=culto -e CONTAS=contas.csv tests/carga/carga.js

# 4. Só a live
k6 run -e BASE=https://mananciapp-render-homolog.onrender.com/ -e CENARIO=live tests/carga/carga.js
```

`-e PESSOAS=100` muda o pico (comece com 50, depois 100, 200, 300).
`-e MENSAGENS=1` faz quem está na live mandar 1 mensagem a cada ~2 min
(grava "Amém! (teste de carga)" no chat da homologação).

### Contas de teste

O teste de verdade precisa de **várias contas de fiel aprovadas** (umas 50):
com uma conta só, todo mundo mexe na mesma linha do banco e o resultado
sai pior que o real. Crie as contas na homologação e um arquivo
`contas.csv` **fora do repositório** (ou na pasta, sem commitar), assim:

```
email,senha
teste01@exemplo.com,SenhaForte01!
teste02@exemplo.com,SenhaForte02!
```

Sem `CONTAS`, dá para usar uma conta só (`-e EMAIL=... -e SENHA=...`) ou
nenhuma (só visitantes: páginas públicas e a live).

O que cada pessoa virtual faz:

- **Navegando**: Home, lista de cursos, um curso e ~4 min de uma aula
  (o player mandando o progresso a cada 30 s, presença a cada 60 s e o
  indicador da live).
- **Na live**: abre a página e acompanha o chat como o navegador (versão da
  lista, de 3 a 10 s conforme o movimento, bloqueio a cada ~30 s), por
  5 min.

Tudo vem de um computador só: com 300 pessoas virtuais, a internet de casa
pode virar o gargalo. Se os números parecerem ruins demais, rodar de novo
com metade e comparar, ou usar o Grafana Cloud k6 (plano grátis).

## Como ler

No fim, o k6 mostra uma tabela. O que importa:

| Linha | Bom | Ruim |
|---|---|---|
| `http_req_failed` | < 1% | Erros 5xx/timeout: servidor ou banco no limite |
| `http_req_duration{tipo:pagina}` p(95) | < 1,5 s | Páginas lentas para 5% das pessoas |
| `http_req_duration{tipo:ajax}` p(95) | < 500 ms | Chat/presença atrasando |
| `http_req_duration{ep:progresso}` p(95) | < 500 ms | O player (o pedido mais pesado, a cada 30 s) |
| `http_req_duration{ep:chat}` p(95) | < 300 ms | Chat da live |
| `checks` | 100% | Login falhando ou página respondendo errado |

As metas estão em `thresholds` no script; reprovado, o k6 sai com código
diferente de 0 e escreve ✗ ao lado.

Durante o teste, olhar também: Render → Metrics (CPU e memória perto de
100%?) e Supabase → Reports (conexões e CPU do banco).

## Resultados

Anotar cada rodada (data, plano do Render, pessoas, p95 páginas/AJAX,
erros), para comparar antes e depois de cada mudança.

| Data | Render | Pessoas | p95 páginas | p95 AJAX | Erros | Observação |
|---|---|---|---|---|---|---|
| | | | | | | |
