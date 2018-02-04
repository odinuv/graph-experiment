FROM neo4j:3.3

RUN apk add --update \
    php7 \
    php7-bcmath \
    php7-mbstring \
  && rm -rf /var/cache/apk/*

RUN mkdir -p /etc/php7/conf.d/
COPY php.ini /etc/php7/conf.d/

ENTRYPOINT ["/docker-entrypoint.sh"]
CMD ["neo4j"]

#CMD ["/bin/sh"]
#WORKDIR /code/

#WORKDIR /var/lib/neo4j 
#CMD ["./docker-entrypoint.sh", "neo4j"]
	
# CMD ["php", "/code/main.php"]
