# Git

##
If you have multiple sites, and want to check status of the gimle submodule in all of them:

```sh
find . -path '*/gimle/.git' -execdir sh -c 'echo -n "\033[32m"; pwd; echo -n "\033[0m"; git status; echo ""' \;
```
