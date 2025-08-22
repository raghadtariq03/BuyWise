# 🛍️ BuyWise - AI-powered Review Verification

**BuyWise** is a bilingual, AI-enhanced product review platform designed to detect fake reviews in real time, support user trust, and gamify the review ecosystem.

---

## 💡 Project Highlights

* 🧠 **AI Review Verification**: Detects fake reviews using a fine-tuned RoBERTa model.
* 🌍 **Bilingual Interface**: Arabic ↔ English with full RTL/LTR support.
* 🎮 **Gamified Engagement**: Points, levels, badges, and redeemable rewards.
* 🔐 **Role-Based Access**: Admins, users, and companies.
* 📣 **Real-Time Notifications**: On likes, replies, and reward claims.

---

## 🛠️ Tech Stack

* **Frontend**: HTML, CSS, JavaScript, Bootstrap
* **Backend**: PHP, MySQL
* **AI Model**: Python (RoBERTa), Flask API
* **Tools**: XAMPP, GitHub, HuggingFace, VS Code

---

## 👩‍💻 Team Members

* **Raghad Tariq Awwad** – Team Leader & Full Stack Development
* **Haneen Maher Ali** – AI Development (RoBERTa, Flask, Dataset)
* **Malak Abdulkareem Zakarneh** – Full Stack Development
* **Islam Emad Al-Rafati** – Full Stack Development

---

## 🎥 Demo Video

[▶️ Watch our project walkthrough](https://youtu.be/gpzpg5rX28M?si=gawNSMz4ET5khY13)

---

## 📂 Documentation

The full PDF report is available in `/docs/BuyWiseDoc.pdf`.

---

## 📦 How to Run Locally

### 1. Clone the Repository

```bash
git clone https://github.com/Aiso03/BuyWise
cd BuyWise
```

### 2. Setup Backend (PHP + MySQL)

* Copy all PHP files and folders (except sensitive config files like `.env`) into your XAMPP `htdocs` directory:

  * Windows: `C:\xampp\htdocs\BuyWise\`
  * macOS/Linux: `/Applications/XAMPP/htdocs/BuyWise/` or `/opt/lampp/htdocs/BuyWise/`

* Start Apache and MySQL via the XAMPP control panel.

* Open your browser and go to:

  ```
  http://localhost/phpmyadmin/
  ```

* Create a new database named `BuyWise`.

* Import the provided `BuyWise.sql` file to set up the database schema and initial data.

### 3. Setup AI Review Verification Model (Separate Setup)

The AI-powered review detection runs as a separate Python Flask API, **not included in this repository**. To use it:

* Download the AI model and API code from Hugging Face:

  [https://huggingface.co/BuyWise/mymodel1h](https://huggingface.co/BuyWise/mymodel1h)

* Follow these steps to set up and run the Flask API:

```bash
# Clone or download the AI API code
git clone https://huggingface.co/BuyWise/mymodel1h
cd mymodel1h

# Create and activate a virtual environment
python -m venv venv
source venv/bin/activate       # macOS/Linux
venv\Scripts\activate          # Windows

# Install required Python packages
pip install -r requirements.txt

# Run the Flask server
python main_custom.py
```

* The Flask API will be accessible at:

  ```
  http://localhost:5000/
  ```

### 4. Access the Application

  ```
  http://localhost/BuyWise/
  ```
