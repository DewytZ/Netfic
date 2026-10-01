# Usamos la versión exacta de Node que usas para evitar discrepancias
FROM node:20-alpine

# Creamos el directorio de trabajo dentro del contenedor
WORKDIR /app

# Copiamos primero los archivos de dependencias
COPY package*.json ./

# Instalamos las dependencias dentro del contenedor
RUN npm install

# Copiamos todo el código fuente del proyecto
COPY . .

# Exponemos el puerto de tu servidor Express
EXPOSE 8000

# Comando por defecto al arrancar el contenedor
CMD ["npm", "run", "dev"]