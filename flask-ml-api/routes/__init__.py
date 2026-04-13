from flask import Blueprint

def register_blueprints(app):
    from .health import health_bp
    from .predict import predict_bp
    from .retrain import retrain_bp
    from .activate import activate_bp

    app.register_blueprint(health_bp)
    app.register_blueprint(predict_bp)
    app.register_blueprint(retrain_bp)
    app.register_blueprint(activate_bp)
