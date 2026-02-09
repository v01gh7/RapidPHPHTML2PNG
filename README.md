# RapidHTML2PNG

PHP-скрипт для конвертации HTML блоков в PNG изображения с прозрачным фоном.

## Overview

RapidHTML2PNG - это PHP-скрипт, который принимает массив HTML блоков через POST API, применяет CSS стили и кэширует результаты. Если хэш контента (HTML + CSS) изменился - пересоздаёт изображение, иначе возвращает кэш.

Проект разработан для работы на shared hosting (PHP 7.4+) с автоматическим определением доступных библиотек рендеринга.

## Features

- **Автоматическое определение библиотек**: wkhtmltoimage > ImageMagick > GD
- **CSS кэширование**: Загружает CSS по URL и кэширует на основе filemtime()
- **Прозрачный фон**: Генерирует PNG с прозрачностью
- **Интеллектуное кэширование**: Использует MD5 хэш для определения необходимости перерендеринга
- **REST API**: Простое интегрирование через POST запрос
- **Безопасность**: Валидация HTML, ограничение размера, sanitization путей

## Requirements

- PHP 7.4 или выше
- PHP расширения: `curl`, `gd`, `mbstring`
- Папка `/assets/media/rapidhtml2png` с правами записи
- (Опционально) wkhtmltoimage для лучшего качества
- (Опционально) ImageMagick для альтернативного рендеринга

## Development Setup

### Using Docker (Рекомендуется)

1. **Клонируйте репозиторий**
   ```bash
   git clone <repository-url>
   cd RapidHTML2PNG
   ```

2. **Запустите setup скрипт**
   ```bash
   chmod +x init.sh
   ./init.sh
   ```

   Скрипт автоматически:
   - Создаст необходимые директории
   - Соберёт Docker контейнер с PHP 7.4
   - Установит все зависимости
   - Запустит development сервер

3. **Доступ к приложению**
   - API Endpoint: `http://localhost:8080/convert.php`
   - Тестовые файлы будут доступны в корне проекта

### Manual Setup (Shared Hosting)

1. **Загрузите файлы на сервер**
   - Все `.php` файлы в корневую директорию
   - Создайте папку `assets/media/rapidhtml2png`
   - Установите права записи: `chmod 755 assets/media/rapidhtml2png`

2. **Проверьте PHP расширения**
   - Убедитесь что установлены: curl, gd, mbstring
   - Проверьте через `php -m` или `phpinfo()`

3. **Тестовый запрос**
   ```bash
   curl -X POST http://yourdomain.com/convert.php \
     -d "html_blocks[]=<div>Test</div>" \
     -d "css_url=http://yourdomain.com/main.css"
   ```

## API Usage

### Endpoint

```
POST /convert.php
Content-Type: multipart/form-data или application/x-www-form-urlencoded
```

### Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `html_blocks[]` | array | Yes | Массив HTML блоков для конвертации |
| `css_url` | string | Yes | URL на CSS файл со стилями |

### Response Format

**Success Response:**
```json
{
  "success": true,
  "data": {
    "images": [
      {
        "hash": "a1b2c3d4e5f6...",
        "url": "/assets/media/rapidhtml2png/a1b2c3d4e5f6....png",
        "cached": false
      }
    ]
  }
}
```

**Error Response:**
```json
{
  "success": false,
  "error": "Invalid CSS URL provided"
}
```

### Example Usage

**PHP (cURL):**
```php
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost:8080/convert.php');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, [
    'html_blocks[]' => '<div class="styled">Hello World</div>',
    'css_url' => 'http://localhost:8080/main.css'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
$data = json_decode($response, true);
```

**JavaScript (fetch):**
```javascript
const formData = new FormData();
formData.append('html_blocks[]', '<div class="styled">Hello World</div>');
formData.append('css_url', '/main.css');

fetch('/convert.php', {
    method: 'POST',
    body: formData
})
.then(res => res.json())
.then(data => console.log(data));
```

## Project Structure

```
RapidHTML2PNG/
├── assets/
│   └── media/
│       └── rapidhtml2png/      # Output directory for PNG files
├── convert.php                  # Main API endpoint
├── Renderer/
│   ├── RendererInterface.php    # Renderer interface
│   ├── WkHtmlToImage.php        # wkhtmltoimage implementation
│   ├── ImageMagick.php          # ImageMagick implementation
│   └── GdRenderer.php           # GD fallback renderer
├── Cache/
│   └── CssCache.php             # CSS caching module
├── HashGenerator.php            # MD5 hash generation
├── LibraryDetector.php          # Library detection
├── docker-compose.yml           # Docker configuration
├── Dockerfile                   # Docker image definition
├── init.sh                      # Setup script
├── test_html_to_render.html     # Test HTML file
├── main.css                     # Test CSS file
└── README.md                    # This file
```

## How It Works

1. **Request Processing**
   - API receives POST request with `html_blocks[]` and `css_url`
   - Валидация входных данных

2. **CSS Loading**
   - Загрузка CSS файла по URL через cURL
   - Проверка filemtime() для определения изменений
   - Кэширование CSS контента

3. **Hash Generation**
   - Генерация MD5 хэша из HTML + CSS контента
   - Хэш используется как уникальное имя файла

4. **Cache Check**
   - Проверка существования файла с таким хэшем
   - Если файл существует - возврат кэшированного пути
   - Если нет - продолжение к рендерингу

5. **Library Detection**
   - Проверка доступности wkhtmltoimage
   - Проверка доступности ImageMagick
   - Проверка доступности GD (всегда доступен как fallback)

6. **Rendering**
   - Рендеринг HTML в PNG используя лучшую доступную библиотеку
   - Применение CSS стилей
   - Установка прозрачного фона

7. **File Saving**
   - Сохранение PNG в `/assets/media/rapidhtml2png/{hash}.png`
   - Возврат URL к созданному файлу

## Testing

### Manual Test

```bash
# Start server
./init.sh

# Test conversion
curl -X POST http://localhost:8080/convert.php \
  -d "html_blocks[]=<div class='test'>Hello World</div>" \
  -d "css_url=http://localhost:8080/main.css"
```

### Verify Output

1. Check console for JSON response
2. Open returned PNG URL in browser
3. Verify:
   - Text is visible
   - CSS styles are applied
   - Background is transparent
   - Image quality is acceptable

## Troubleshooting

### Docker Issues

**Container won't start:**
```bash
docker-compose logs app
```

**Permission denied on output directory:**
```bash
docker-compose exec app chown -R www-data:www-data /var/www/html/assets
```

### PHP Issues

**Extensions not loaded:**
- Check phpinfo() for loaded extensions
- Ensure docker image was built correctly

**wkhtmltoimage not working:**
- Check logs for exec() errors
- Verify binary exists: `which wkhtmltoimage`
- May need to install manually in container

### Rendering Issues

**PNG not created:**
- Check directory permissions
- Verify no size limit errors in PHP logs
- Check if rendering library is actually available

**Transparent background not working:**
- Verify library supports transparency
- Check if CSS is forcing background color
- Try different rendering library

## License

[Specify your license here]

## Contributing

[Specify contribution guidelines here]

## Support

For issues and questions, please open an issue on GitHub.
