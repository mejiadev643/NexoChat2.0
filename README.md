# NexoChat - Guía de Inicio

## Requisitos Previos

- PHP >= 8.1
- Composer
- MySQL o PostgreSQL

## Instalación

1. **Clona el repositorio:**
    ```bash
    git clone https://github.com/mejiadev643/NexoChat.git
    cd NexoChat
    ```

2. **Instala dependencias de PHP:**
    ```bash
    composer install
    ```

3. **Copia el archivo de entorno y configura tus variables:**
    ```bash
    cp .env.example .env
    # Edita .env con tus credenciales de base de datos y otros datos necesarios, importante las variables de reberb
    ```

4. **Genera la clave de la aplicación:**
    ```bash
    php artisan key:generate
    ```

## Migraciones

5. **Ejecuta las migraciones de la base de datos:**
    ```bash
    php artisan migrate
    ```

## Levanta el servidor

6. **Inicia el servidor de desarrollo de Laravel:**
    ```bash
    php artisan serve
    ```

## Laravel Reverb

7. **Levanta el servidor de Laravel Reverb:**
    ```bash
    php artisan reverb:start
    ```

---

¡Listo! Accede a tu proyecto en [http://localhost:8000](http://localhost:8000).
