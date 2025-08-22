
import os
import torch
import torch.nn as nn
from flask import Flask, jsonify, send_from_directory, request
from langdetect import detect
from transformers import RobertaTokenizer, RobertaModel
from flask_cors import CORS

app = Flask(__name__)
CORS(app)

# ======= Define the same model class used in training =======
class RobertaBinaryClassifier(nn.Module):
    def __init__(self, base_model):
        super().__init__()
        self.roberta = base_model
        self.dropout = nn.Dropout(0.1)
        self.classifier = nn.Linear(base_model.config.hidden_size, 2)  # 2 classes

    def forward(self, input_ids, attention_mask):
        outputs = self.roberta(input_ids=input_ids, attention_mask=attention_mask)
        cls_hidden_state = outputs[0][:, 0, :]
        cls_hidden_state = self.dropout(cls_hidden_state)
        logits = self.classifier(cls_hidden_state)
        return logits

# ======= Load tokenizer, model, weights =======
device = torch.device("cuda" if torch.cuda.is_available() else "cpu")
tokenizer = RobertaTokenizer.from_pretrained("./AI/mytokenizer", use_fast=True, local_files_only=True)
base_model = RobertaModel.from_pretrained("roberta-base")
model = RobertaBinaryClassifier(base_model)

# ✅ حمّل أوزان النموذج بشكل صحيح
state_dict = torch.load("./AI/mymodel1h.pt", map_location=device)
model.load_state_dict(state_dict)

model.to(device)



model.eval()

print(type(model))

# ======= Utility =======
def is_english(text: str) -> bool:
    try:
        return detect(text) == "en"
    except:
        return False

# ======= Routes =======
@app.route("/")
def home():
    return "Welcome to the Fake Review Detection API"

@app.route("/predict", methods=["POST"])
def predict():
    try:
        data = request.get_json(silent=True)
        if not data or "text" not in data:
            return jsonify({"error": "No 'text' field in JSON"}), 400

        text = data["text"]
        if not is_english(text):
            return jsonify({"error": "Non-English input"}), 400

        enc = tokenizer(
            text,
            truncation=True,
            padding="max_length",
            max_length=tokenizer.model_max_length,
            return_tensors="pt"
        )

        input_ids = enc["input_ids"].to(device)
        attention_mask = enc["attention_mask"].to(device)

        with torch.no_grad():
            logits = model(input_ids=input_ids, attention_mask=attention_mask)
            probs = torch.softmax(logits, dim=1).cpu()[0].tolist()

        fake_prob, real_prob = probs
        return jsonify({
            "fake_prob": fake_prob,
            "real_prob": real_prob,
            "prediction": "real" if real_prob >= 0.5 else "fake"
        }), 200
  
    except Exception as e:
        return jsonify({"error": str(e)}), 500

#@app.route("/app")
#def serve_frontend():
#    current_dir = os.path.dirname(os.path.abspath(__file__))
#    return send_from_directory(current_dir, "index_final.html")

if __name__ == "__main__":
    app.run(host="0.0.0.0", port=5000)
