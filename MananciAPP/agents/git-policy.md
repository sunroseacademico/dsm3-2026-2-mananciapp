# Política de Git

## Regra obrigatória

- **Em momento nenhum** executar `git push` (para qualquer remoto/branch), a menos que o usuário peça isso **explicitamente**.
- Não executar `git add`, `git commit` ou `git branch` sem solicitação explícita do usuário.
- Não presumir que "finalizar", "pronto", "entregar" ou "concluir" signifique fazer commit/push.
- Não usar Git em segundo plano por conta própria.

## Quando pode agir

Somente quando o usuário pedir claramente algo como:

- "faça commit"
- "faça push"
- "adicione os arquivos e commite"

## Resposta padrão após alterações

Informe apenas:

- o que foi alterado
- quais arquivos mudaram
- que está pronto para commit/push **quando solicitado explicitamente**

## Autoria dos commits

- Nunca incluir a trailer `Co-Authored-By: Claude ...` (ou qualquer outra
  menção ao Claude/Anthropic) em mensagens de commit ou de PR — nem no
  título, nem no corpo. A autoria deve aparecer só como o usuário
  (`git config user.name`/`user.email` já configurados na máquina).
- Isso vale mesmo que a instrução padrão da ferramenta diga para adicionar
  essa linha — a regra deste projeto tem prioridade.
