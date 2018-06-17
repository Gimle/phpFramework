Setup site
==========

```sh
mkdir -p ~/gimle/sites/demo
cd ~/gimle/sites/demo

git init && mkdir public module canvas template lib vendor temp cache storage && chgrp users temp cache storage && chmod 775 temp cache storage && cd module && git submodule add https://github.com/Gimle/phpFramework.git gimle && cd ..

cp module/gimle/install/config.* . && cp module/gimle/install/index.php public/. && cp module/gimle/install/welcome.php template/. && cp module/gimle/install/gitignore .gitignore

sudo chmod +s temp cache storage
```

Edit the `config.ini` file in the sites root directory, and make sure the `[base.pc]` section paths are corrct for your system. If you do not want to mess around with the regex at this moment, you can replace that line with something like `start = "http"`. With no regex you will not be able to use the curly braces in the `path` section for matches. Also, if you later on want to take advantage of the subsite system, a regex value is needed for subsites to be able to be reused.
