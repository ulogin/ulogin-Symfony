# uLogin

Tested up to: 3.4.8
Stable tag: 1.0.1
License: GPLv2

**uLogin** — это инструмент, который позволяет пользователям получить единый доступ к различным Интернет-сервисам без необходимости повторной регистрации,
а владельцам сайтов — получить дополнительный приток клиентов из социальных сетей и популярных порталов (Google, Яндекс, Mail.ru, ВКонтакте, Facebook и др.)


## Установка

**Обратите внимание**: бандл работает только в связке с [FOSUserBundle](https://github.com/FriendsOfSymfony/FOSUserBundle) !

1. Установить пакет через `composer`
2. Добавить в AppKernel.php строку `new Ulogin\AuthBundle\UloginAuthBundle(),`
3. В своем .twig шаблоне добавить вызов
    ```
    {{ include('UloginAuthBundle::widget.html.twig', { "uLoginID": "123456", "label": "Войти с помощью:" }) }}
    ```
    где
    `uLoginID` - ID виджета из личного кабинета на сайте http://ulogin.ru
    `label` - текст около виджета. Необязательный параметр. Может быть передана пустая строка, тогда надписи не будет.

4. В `services.yml` внести:
    ```
    services:
        security.success_login_handler:
            class: '\Symfony\Component\Security\Http\Authentication\DefaultAuthenticationSuccessHandler'
            public: true
    ```
    Иначе будет выброшено исключение после успешного логина.
5. В `parameters.yml` внести:
   ```
    parameters:
        ulogin_auth.secret_key: 'СЛУЧАЙНАЯ СТРОКА'
   ```
   Иначе будут создаваться предсказуемые пароли для новых пользователей.
   Значение можно получить на https://www.random.org/strings/ или запустить `base64 < /dev/urandom | head -n 1` на Linux

### Настройка генерации имён пользователей
_Черновик, пока не работает :(_

`services.yml`:
```
services:
  Ulogin\AuthBundle\Controller\AuthController:
    class: 'Ulogin\AuthBundle\Controller\AuthController'
    arguments:
      -
        '!php/const:\Ulogin\AuthBundle\Controller\AuthController::OPT_ALLOW_SHORT_NAMES': false
        '!php/const:\Ulogin\AuthBundle\Controller\AuthController::OPT_NAMES_WITH_NETWORK': true
```

## Дополнительная информация

Поддерживается добавление фото юзера. Для этого у сущности пользователя должны присутствовать методы setPhoto и getPhoto.
При необходимости имена методов можно изменить в файле AuthController.php


Чтобы создать свой виджет для входа на сайт достаточно зайти в Личный Кабинет (ЛК) на сайте http://ulogin.ru/lk.php,
добавить свой сайт к списку "Мои сайты" и на вкладке "Виджеты" добавить новый виджет. Вы можете редактировать свой виджет самостоятельно.
**Важно**: Для успешной работы плагина необходимо включить в обязательных полях профиля поле Еmail в Личном кабинете uLogin.

## Changelog

### 1.1.0
* Добавлен composer.json
* Исправлен README.md по опыту реальной установки и отладки
* WIP Отладка работы с Symfony 3.4.6

### 1.0.1
* Добавлена поддержка фото юзера.
* Внесены некоторые правки в код виджета, позволяющие ему без проблем работать при подгрузке аяксом.
