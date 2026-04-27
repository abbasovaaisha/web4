<?php
/**
 * Задание 4 – валидация через регулярные выражения, cookies, GET-перезагрузка
 */

// ---------- Функции работы с БД ----------
function connectToDatabase() {
    static $db = null;
    if ($db === null) {
        $host = 'localhost';
        $user = 'u82462';
        $pass = '9164341';
        $name = 'u82462';
        $dsn = "mysql:host=$host;dbname=$name;charset=utf8mb4";
        try {
            $db = new PDO($dsn, $user, $pass);
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            exit('Ошибка подключения к БД: ' . $e->getMessage());
        }
    }
    return $db;
}

function getLanguageList() {
    $pdo = connectToDatabase();
    $stmt = $pdo->query("SELECT id, name FROM programming_languages ORDER BY name");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ---------- Вспомогательные функции для Cookies ----------
function setJsonCookie($name, $data, $expire = 0) {
    setcookie($name, json_encode($data, JSON_UNESCAPED_UNICODE), $expire, '/', '', false, true);
}

function getJsonCookie($name) {
    if (isset($_COOKIE[$name])) {
        $data = json_decode($_COOKIE[$name], true);
        if (is_array($data)) return $data;
    }
    return null;
}

function deleteCookie($name) {
    setcookie($name, '', time() - 3600, '/');
}

// ---------- Белые списки ----------
$allowedLanguages = [
    'Pascal', 'C', 'C++', 'JavaScript', 'PHP', 'Python',
    'Java', 'Haskell', 'Clojure', 'Prolog', 'Scala', 'Go'
];
$allowedGenders = ['male', 'female'];

// Примеры правильного заполнения (для подсказок)
$fieldExamples = [
    'full_name' => 'Пример: Иванов Иван Иванович',
    'phone'     => 'Пример: +7 999 123-45-67',
    'email'     => 'Пример: ivanov@mail.ru',
    'birth_date'=> 'ГГГГ-ММ-ДД',
    'gender'    => 'Выберите вариант',
    'languages' => 'Выберите хотя бы один язык',
    'bio'       => 'До 10000 символов',
    'contract_agreed' => 'Требуется подтверждение'
];

// ---------- Обработка POST-запроса (отправка формы) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Получение данных
    $formData = [
        'full_name'       => trim($_POST['full_name'] ?? ''),
        'phone'           => trim($_POST['phone'] ?? ''),
        'email'           => trim($_POST['email'] ?? ''),
        'birth_date'      => trim($_POST['birth_date'] ?? ''),
        'gender'          => $_POST['gender'] ?? '',
        'bio'             => trim($_POST['bio'] ?? ''),
        'contract_agreed' => isset($_POST['contract_agreed']),
        'languages'       => $_POST['languages'] ?? []
    ];

    $errors = [];

    // ---- 1. ФИО ----
    if ($formData['full_name'] === '') {
        $errors['full_name'] = 'Поле обязательно для заполнения.';
    } elseif (!preg_match('/^[a-zA-Zа-яА-ЯёЁ\s\-]+$/u', $formData['full_name'])) {
        $errors['full_name'] = 'Допустимы только буквы, пробелы и дефис. ' . $fieldExamples['full_name'];
    } elseif (strlen($formData['full_name']) > 150) {
        $errors['full_name'] = 'Максимальная длина — 150 символов.';
    } else {
        preg_match_all('/[a-zA-Zа-яА-ЯёЁ]/u', $formData['full_name'], $letters);
        if (count($letters[0]) < 2) {
            $errors['full_name'] = 'В имени должно быть не менее двух букв.';
        }
    }

    // ---- 2. Телефон (регулярное выражение на допустимые символы и формат) ----
    if ($formData['phone'] === '') {
        $errors['phone'] = 'Поле обязательно для заполнения.';
    } elseif (!preg_match('/^\+7[\s\(]*[0-9]{3}[\)\s]*[0-9]{3}[\s\-]*[0-9]{2}[\s\-]*[0-9]{2}$/', $formData['phone'])) {
        $errors['phone'] = 'Недопустимый формат. Допустимые символы: +, цифры, пробелы, скобки, дефис. ' . $fieldExamples['phone'];
    } else {
        $digits = preg_replace('/\D/', '', $formData['phone']);
        if (strlen($digits) !== 11) {
            $errors['phone'] = 'Номер должен содержать ровно 11 цифр.';
        } elseif ($digits[0] !== '7') {
            $errors['phone'] = 'Номер должен начинаться с 7.';
        }
    }

    // ---- 3. Email (регулярное выражение) ----
    if ($formData['email'] === '') {
        $errors['email'] = 'Поле обязательно для заполнения.';
    } elseif (!preg_match('/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', $formData['email'])) {
        $errors['email'] = 'Некорректный email. Допустимы латинские буквы, цифры, точки, дефис, подчёркивание, плюс. ' . $fieldExamples['email'];
    }

    // ---- 4. Дата рождения ----
    if ($formData['birth_date'] === '') {
        $errors['birth_date'] = 'Поле обязательно для заполнения.';
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $formData['birth_date'])) {
        $errors['birth_date'] = 'Неверный формат. Используйте ГГГГ-ММ-ДД.';
    } else {
        $dateObj = DateTime::createFromFormat('Y-m-d', $formData['birth_date']);
        if (!$dateObj || $dateObj->format('Y-m-d') !== $formData['birth_date']) {
            $errors['birth_date'] = 'Некорректная дата.';
        } elseif ($dateObj > new DateTime('today')) {
            $errors['birth_date'] = 'Дата не может быть в будущем.';
        }
    }

    // ---- 5. Пол ----
    if ($formData['gender'] === '') {
        $errors['gender'] = 'Выберите пол.';
    } elseif (!preg_match('/^(male|female)$/', $formData['gender'])) {
        $errors['gender'] = 'Недопустимое значение.';
    }

    // ---- 6. Языки программирования ----
    if (empty($formData['languages'])) {
        $errors['languages'] = 'Необходимо выбрать хотя бы один язык.';
    } else {
        foreach ($formData['languages'] as $lang) {
            if (!in_array($lang, $allowedLanguages)) {
                $errors['languages'] = 'Выбран недопустимый язык.';
                break;
            }
        }
    }

    // ---- 7. Биография ----
    if (strlen($formData['bio']) > 10000) {
        $errors['bio'] = 'Текст слишком длинный (максимум 10000 символов).';
    } elseif (!preg_match('/^[a-zA-Zа-яА-ЯёЁ0-9\s\.,!?;:\'\"\-\(\)@#$%&*+=<>~`|\\\/\[\]{}]*$/u', $formData['bio'])) {
        $errors['bio'] = 'Биография содержит недопустимые символы. Разрешены буквы, цифры, пробелы, знаки препинания.';
    }

    // ---- 8. Чекбокс ----
    if (!$formData['contract_agreed']) {
        $errors['contract_agreed'] = 'Необходимо подтвердить ознакомление с контрактом.';
    }

    // ---- Решение: редирект с Cookies или сохранение в БД ----
    if (!empty($errors)) {
        // Сохраняем ошибки и введённые данные в сессионные Cookies
        setJsonCookie('form_errors', $errors, 0);
        setJsonCookie('sticky_form_data', $formData, 0);
        // Редирект методом GET
        header('Location: ' . $_SERVER['SCRIPT_NAME']);
        exit;
    } else {
        // Успешная валидация – сохраняем в БД
        try {
            $pdo = connectToDatabase();
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                INSERT INTO applications 
                (full_name, phone, email, birth_date, gender, bio, contract_agreed)
                VALUES (:fn, :ph, :em, :bd, :gen, :bio, :ca)
            ");
            $stmt->execute([
                ':fn'  => $formData['full_name'],
                ':ph'  => $formData['phone'],
                ':em'  => $formData['email'],
                ':bd'  => $formData['birth_date'],
                ':gen' => $formData['gender'],
                ':bio' => $formData['bio'],
                ':ca'  => $formData['contract_agreed'] ? 1 : 0
            ]);
            $applicationId = $pdo->lastInsertId();

            // Сопоставление имен языков с ID
            $langRecords = getLanguageList();
            $languageMap = [];
            foreach ($langRecords as $lang) {
                $languageMap[$lang['name']] = $lang['id'];
            }

            $linkStmt = $pdo->prepare("INSERT INTO application_languages (application_id, language_id) VALUES (?, ?)");
            foreach ($formData['languages'] as $langName) {
                if (isset($languageMap[$langName])) {
                    $linkStmt->execute([$applicationId, $languageMap[$langName]]);
                }
            }

            $pdo->commit();

            // Сохраняем данные в постоянную Cookie на 1 год
            unset($formData['contract_agreed']); // чекбокс не сохраняем как предзаполнение
            setJsonCookie('default_form_data', $formData, time() + 365*24*3600);
            // Флаг успешного сохранения для flash-сообщения
            setJsonCookie('success_flash', ['message' => 'Данные успешно сохранены!'], 0);

            header('Location: ' . $_SERVER['SCRIPT_NAME']);
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            // Ошибка БД – показываем как обычную ошибку формы
            setJsonCookie('form_errors', ['database' => 'Ошибка сохранения: ' . $e->getMessage()], 0);
            setJsonCookie('sticky_form_data', $formData, 0);
            header('Location: ' . $_SERVER['SCRIPT_NAME']);
            exit;
        }
    }
}

// ---------- GET-запрос: загружаем данные из Cookies ----------
$formInput = [
    'full_name' => '',
    'phone'     => '',
    'email'     => '',
    'birth_date'=> '',
    'gender'    => '',
    'bio'       => '',
    'contract_agreed' => false,
    'languages' => []
];
$errorList = [];
$successMessage = '';

// 1. Если есть ошибки из Cookies (после неудачной отправки)
$stickyErrors = getJsonCookie('form_errors');
$stickyData = getJsonCookie('sticky_form_data');
if ($stickyErrors !== null && $stickyData !== null) {
    $errorList = $stickyErrors;
    $formInput = $stickyData;
    // Удаляем Cookies после использования
    deleteCookie('form_errors');
    deleteCookie('sticky_form_data');
} 
// 2. Иначе пробуем взять сохранённые по умолчанию данные (на 1 год)
else {
    $defaultData = getJsonCookie('default_form_data');
    if ($defaultData !== null) {
        $formInput = array_merge($formInput, $defaultData);
    }
}

// 3. Flash-сообщение об успехе
$successFlash = getJsonCookie('success_flash');
if ($successFlash !== null && isset($successFlash['message'])) {
    $successMessage = $successFlash['message'];
    deleteCookie('success_flash');
}

// Получаем список языков для отображения в форме
$languageOptions = getLanguageList();
if (empty($languageOptions)) {
    $languageOptions = array_map(function($name) {
        return ['id' => $name, 'name' => $name];
    }, $allowedLanguages);
}

// Подключаем шаблон формы
require 'anketa.php';
?>