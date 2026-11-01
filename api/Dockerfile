# api service: PDF data extraction (bank statements, payslips)
FROM python:3.12-slim
WORKDIR /app
COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt
COPY app.py extract.py ./
RUN useradd -r -u 1001 api
USER api
EXPOSE 5000
CMD ["gunicorn", "-b", "0.0.0.0:5000", "-w", "2", "--timeout", "90", "app:app"]
