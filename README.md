# Helpdesk

Projeto Helpdesk.

## Configuracao local

Esta pasta foi preparada com configuracao padrao de Git e arquivos base para desenvolvimento.

Como a pasta `.git` do ambiente atual esta montada como somente leitura, o repositorio local foi inicializado em `.git-local`.

Para usar Git nesta pasta neste ambiente:

```bash
git --git-dir=.git-local --work-tree=. status
```

Quando a pasta `.git` puder ser substituida normalmente, mova `.git-local` para `.git`.
