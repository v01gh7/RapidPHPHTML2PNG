# MODX Revo 2.8 Integration (RapidHTML2PNG)

Интеграция добавляет в менеджер MODX кнопку `Отрендерить` в шапке и модальное окно с двумя режимами:
- `Отрендерить все ресурсы`
- `Отрендерить по ID`

Пайплайн:
1. Берется шаблон ресурса.
2. Шаблон рендерится через `pdoTools` (подстановка данных ресурса и TV).
3. Из итогового HTML собираются блоки с текстом.
4. Блоки отправляются batch-ами в `convert.php` (`html_blocks[]`).

## 0. Готовый transport package

Готовый архив уже лежит в репозитории:
- `integrations/modx/rapidhtml2png-1.0.0-pl.transport.zip`

Его можно сразу загружать в MODX:
- `Extras -> Installer -> Upload a package`

## Сборка через Docker (PHP 7.4)

Dockerfile для сборки пакета:
- `integrations/modx/ready_pocket/Dockerfile.package`

Команды PowerShell из корня проекта:

```powershell
docker build -f .\integrations\modx\ready_pocket\Dockerfile.package -t rapidhtml2png-modx-packager .\integrations\modx\ready_pocket
$src = (Resolve-Path .\integrations\modx\ready_pocket).Path
docker run --rm -v "${src}:/work" -w /work rapidhtml2png-modx-packager bash -lc "composer config --global audit.block-insecure false && composer install --no-interaction --no-progress && composer run build"
Copy-Item .\integrations\modx\ready_pocket\_packages\rapidhtml2png-1.0.0-pl.transport.zip .\integrations\modx\rapidhtml2png-1.0.0-pl.transport.zip -Force
```

## 1. Что нужно до установки

- MODX Revo `2.8.x`
- Установленный `pdoTools`
- Рабочий endpoint конвертера RapidHTML2PNG (например: `https://example.com/convert.php`)

## 2. Копирование файлов

Скопируйте файлы из этой папки в корень MODX:

- `integrations/modx/assets/components/rapidhtml2png/js/mgr/rapidhtml2png.js`
  -> `assets/components/rapidhtml2png/js/mgr/rapidhtml2png.js`
- `integrations/modx/core/components/rapidhtml2png/elements/plugins/rapidhtml2png_header_button.plugin.php`
  -> `core/components/rapidhtml2png/elements/plugins/rapidhtml2png_header_button.plugin.php`
- `integrations/modx/core/components/rapidhtml2png/model/rapidhtml2png/RendererService.php`
  -> `core/components/rapidhtml2png/model/rapidhtml2png/RendererService.php`
- `integrations/modx/connectors/rapidhtml2png.php`
  -> `connectors/rapidhtml2png.php`

## 3. Системные настройки MODX

Создайте настройки (`System Settings`) с namespace `core` (или своим):

- `rapidhtml2png_convert_url` = `https://example.com/convert.php`
- `rapidhtml2png_convert_api_key` = `YOUR_CONVERTER_API_KEY`
- `rapidhtml2png_css_url` = `https://example.com/main.css` (опционально)
- `rapidhtml2png_render_engine` = `auto` (или `gd`, `imagick`, `wkhtmltoimage`)
- `rapidhtml2png_request_timeout` = `120`
- `rapidhtml2png_batch_max_bytes` = `4500000`
- `rapidhtml2png_batch_max_blocks` = `75`
- `rapidhtml2png_default_skip_classes` = `no-render,skip-export` (опционально)

## 4. Создание plugin в MODX

1. `Elements -> Plugins -> New Plugin`
2. Name: `RapidHTML2PNGHeaderButton`
3. Plugin code: вставьте код из файла  
   `core/components/rapidhtml2png/elements/plugins/rapidhtml2png_header_button.plugin.php`
4. На вкладке `System Events` включите:
   - `OnManagerPageBeforeRender`
5. Сохраните plugin.

## 5. Как использовать

1. Откройте manager MODX и обновите страницу.
2. В шапке появится кнопка `Отрендерить`.
3. Нажмите кнопку:
   - `Отрендерить все ресурсы` -> обработка всех не удаленных ресурсов.
   - `Отрендерить по ID` -> обработка только ID из поля (`1,2,10`).
4. Поле `CSS классы пропуска` принимает список классов через запятую.  
   Любой HTML-узел с таким классом (или внутри такого узла) пропускается.

## 6. Ответ connector

Connector `connectors/rapidhtml2png.php` возвращает JSON:

- `success` (bool)
- `message` (summary)
- `data`:
  - `rendered_resources`
  - `total_blocks`
  - `batches_total`
  - `batches_success`
  - `batches_failed`
  - `output_files`
  - `resource_stats`
  - `resource_errors`
  - `batch_results`

## 7. Ограничения текущей реализации

- Извлекаются текстовые DOM-блоки после рендера шаблона (не raw content).
- Для минимизации дублей блоки дедуплицируются по `md5`.
- При больших объемах используются batch-и по размеру и количеству.
- Режим `all` берет все ресурсы с `deleted = 0`.
