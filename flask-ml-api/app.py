import os
from flask import Flask
from model_registry import load_saved_models
from routes import register_blueprints

app = Flask(__name__)

# Register all routes via blueprints
register_blueprints(app)

# Attempt to load saved models right away
try:
    load_saved_models()
except Exception:
    pass

if __name__ == "__main__":
    flask_host = os.getenv("FLASK_HOST", "0.0.0.0")
    flask_port = int(os.getenv("FLASK_PORT", "5000"))
    app.run(host=flask_host, port=flask_port)
