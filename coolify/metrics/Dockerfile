# syntax=docker/dockerfile:1
FROM python:3.13-alpine
WORKDIR /app
RUN mkdir -p /data && chown 101:101 /data
COPY --chown=101:101 metrics/server.py /app/server.py
USER 101:101
EXPOSE 8080
CMD ["python", "/app/server.py"]
