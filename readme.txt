como correr el proyecto:

1. meter la carpeta del proyecto a la carpeta htdocs de xampp:
    disco local c:/xampp/htdocs
2. iniciar los servicios de apache y mysql en xampp
3. darle en el admin del mysql dentro del programa xampp
4. estando el el php myadmin hacer lo siguiente:
    4.1. dar en nueva
    4.2. darle el nombre a la base de datos de: supermercado_db y dar en crear
    4.3. despues de crearla se dirige a importar y selecciona el archivo schema.sql que se
    encuentra en la carpeta de database del proyecto
    4.4. por ultimo importa los datos de prueba de la misma manera
    que seria el archivo seed.sql, este se importa igual que el schema.sql
5. correr el sistema colocando la url en el navegador
    http://localhost/aplicacion_ventas_supermercado/index.php?ruta=auth/login
    La URL base se detecta automaticamente segun el nombre de la carpeta en htdocs.

IMPORTANTE

1. descargar git en el computador, configurar y vincular luego en el vscode la cuenta de github con el repositorio
   donde se encuentra el proyecto.
2. clonar el repositorio en el vscode con el comando:
   git clone https://github.com/walker11gg/aplicacion_ventas_supermercado
3. realizar los cambios correspondientes, probar y verificar que todo funcione
   antes de subir cambios a github.
4. comandos para subir a github desde visual:
   git add .
   git commit -m "descripcion de lo realizado"
   git push
