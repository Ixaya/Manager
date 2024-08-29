# Admin con VUE 3

## Requerimientos

Node 18 o superior
NPM 10 o superior

Se debes de crear tus archivos que tendran las variables de entorno

.env

.env.dev

.env.prod

en el .env van las variables globales dentro del archivo esta comentado que es cada cosa y las opciones que se pueden utilizar. Una vez que ya esten listas las variables de entorno puede seguir con el siguiente paso.

### Utilizando NPM

1. Instalar dependencias

```
npm install
```

2. Ejecutar en desarrollo esto es para trabajar en local 

```
npm run start:dev

Si corre de manera correcta en windows sale algo asi 

 App running at:
  - Local:   http://localhost:8083/
  - Network: http://192.168.1.112:8083/
```

3. Build para production

```
npm run build

Al ejecutar ese comando y todo sale bien en windows sale algo asi 


 DONE  Build complete. The dist directory is ready to be deployed.
 INFO  Check out deployment instructions at https://cli.vuejs.org/guide/deployment.html
 
 Tu carpeta dist se debe de crear en raiz, de momento ese archivo dist lo mueves a tu carpeta public/assets/admin/default/ esta seria la ruta donde deberias poner tu carpeta dist. 
 Esta misma ruta esta en el .env y tu index.html que esta en dist lo copias y lo pegas en vue/admin con esto ya podrias ver los cambios en el servidor o desde manager en local.
```
