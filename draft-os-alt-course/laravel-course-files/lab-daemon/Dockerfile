FROM python:3.12-slim-bookworm
WORKDIR /app
RUN apt-get update && apt-get install -y --no-install-recommends \
    ca-certificates curl \
    && curl -fsSL "https://download.docker.com/linux/static/stable/x86_64/docker-27.3.1.tgz" -o /tmp/d.tgz \
    && tar -xzf /tmp/d.tgz -C /tmp && mv /tmp/docker/docker /usr/local/bin/docker && rm -rf /tmp/d.tgz /tmp/docker \
    && curl -fsSL "https://github.com/tsl0922/ttyd/releases/download/1.7.7/ttyd.x86_64" -o /usr/local/bin/ttyd \
    && chmod +x /usr/local/bin/ttyd \
    && rm -rf /var/lib/apt/lists/*
COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt
COPY app.py .
ENV PYTHONUNBUFFERED=1
EXPOSE 8090
CMD ["uvicorn", "app:app", "--host", "0.0.0.0", "--port", "8090"]
