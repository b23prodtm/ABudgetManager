# api service: PDF data extraction (bank statements, payslips)
FROM python:3.12-slim
WORKDIR /app
COPY requirements.txt .
RUN apt-get update && apt-get install -y --no-install-recommends \
        gcc libc6-dev libffi-dev libjpeg-dev zlib1g-dev \
    && pip install --no-cache-dir -r requirements.txt \
    && apt-get purge -y --auto-remove gcc libc6-dev libffi-dev libjpeg-dev zlib1g-dev \
    && apt-get install -y --no-install-recommends libjpeg62-turbo libffi8 zlib1g \
    && rm -rf /var/lib/apt/lists/*
COPY app.py extract.py ./
RUN useradd -r -u 1001 api
USER api
EXPOSE 5000
CMD ["gunicorn", "-b", "0.0.0.0:5000", "-w", "2", "--timeout", "90", "app:app"]
