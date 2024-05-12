FROM node:current-alpine
WORKDIR /var/www/html/fe
COPY ./src/fe/package*.json /var/www/html/fe
RUN npm install
COPY ./src/fe/ /var/www/html/fe
EXPOSE 3000
RUN npm run build
CMD [ "node", ".output/server/index.mjs" ]
