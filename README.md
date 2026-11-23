# MananciAPP

Plataforma de ensino da Missão Evangélica Manancial: cursos em vídeo,
comunidade, transmissão ao vivo e painel administrativo.

O código fica em [MananciAPP/](MananciAPP/). Comece por:

- [MananciAPP/README.md](MananciAPP/README.md): o que o app faz e como rodar
  localmente;
- [MananciAPP/DEPLOY_RENDER.md](MananciAPP/DEPLOY_RENDER.md): como está
  publicado (Render) e como funcionam o banco e os arquivos;
- [MananciAPP/docs/interno/pontos_gerais.md](MananciAPP/docs/interno/pontos_gerais.md):
  o que ainda falta (configuração, segurança, desempenho, qualidade);
- [MananciAPP/docs/README.md](MananciAPP/docs/README.md): índice da
  documentação (cliente e interna).

| Parte | Onde roda |
|---|---|
| Aplicação PHP (login, área do fiel, admin) | Render, via Docker |
| Banco de dados | PostgreSQL no Supabase |
| Vídeos das aulas e capas dos cursos | Cloudflare R2 (bucket privado, URLs assinadas) |
| Transmissão ao vivo | YouTube (player embutido) |
