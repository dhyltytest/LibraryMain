<?php

session_start();

require_once 'db.php';
require_once 'functions.php';

/* ==========================================
   REQUIRE LOGIN
   ========================================== */

require_login();

/* ==========================================
   SEARCH
   ========================================== */

$search = '';

if (isset($_GET['search'])) {
    $search = trim($_GET['search']);
}

/* ==========================================
   GET USERS
   ========================================== */

$result = null;

try {
    $conn = connect_db();
    
    if ($search !== '') {
        $search_value = "%" . $search . "%";
        
        $stmt = $conn->prepare(
            "SELECT id, full_name, email, phone, organization, user_type
            FROM users
            WHERE
                CAST(id AS CHAR) LIKE ?
                OR full_name LIKE ?
                OR email LIKE ?
                OR phone LIKE ?
                OR organization LIKE ?
                OR user_type LIKE ?
            ORDER BY id DESC"
        );
        
        $stmt->bind_param(
            "ssssss",
            $search_value,
            $search_value,
            $search_value,
            $search_value,
            $search_value,
            $search_value
        );
        
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();
    } else {
        $result = $conn->query(
            "SELECT id, full_name, email, phone, organization, user_type
            FROM users
            ORDER BY id DESC"
        );
    }
} catch (Exception $e) {
    die("Database error: " . $e->getMessage());
}

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Master List - BookShare
    </title>


    <style>

        * {

            box-sizing: border-box;

            font-family: Arial, sans-serif;

        }


        body {

            margin: 0;

            background: #f4f7fb;

            padding: 30px;

        }


        /* ==========================================
           HEADER
           ========================================== */

        .header {

            background: #2563eb;

            color: white;

            padding: 25px 35px;

            border-radius: 10px;

            margin-bottom: 25px;

        }


        .header h1 {

            margin: 0;

            font-size: 30px;

        }


        .header p {

            margin: 7px 0 0;

            font-size: 15px;

        }


        /* ==========================================
           MAIN CONTAINER
           ========================================== */

        .container {

            width: 100%;

            background: white;

            padding: 25px;

            border-radius: 10px;

            box-shadow:
                0 5px 15px
                rgba(0, 0, 0, 0.08);

            overflow-x: auto;

        }


        /* ==========================================
           TOP BAR
           ========================================== */

        .top-bar {

            display: flex;

            justify-content: space-between;

            align-items: center;

            margin-bottom: 20px;

            gap: 15px;

        }


        .top-bar h2 {

            margin: 0;

            color: #222;

        }


        .back-button {

            text-decoration: none;

            background: #333;

            color: white;

            padding: 10px 15px;

            border-radius: 5px;

            white-space: nowrap;

        }


        .back-button:hover {

            background: #222;

        }


        /* ==========================================
           SEARCH BAR
           ========================================== */

        .search-container {

            display: flex;

            gap: 10px;

            margin-bottom: 25px;

            width: 100%;

        }


        .search-input {

            flex: 1;

            padding: 13px 15px;

            border: 1px solid #ccc;

            border-radius: 6px;

            font-size: 15px;

            outline: none;

        }


        .search-input:focus {

            border-color: #2563eb;

            box-shadow:
                0 0 0 2px
                rgba(37, 99, 235, 0.10);

        }


        .search-button {

            background: #2563eb;

            color: white;

            border: none;

            padding: 13px 20px;

            border-radius: 6px;

            font-size: 15px;

            font-weight: bold;

            cursor: pointer;

        }


        .search-button:hover {

            background: #1d4ed8;

        }


        .clear-button {

            background: #6b7280;

            color: white;

            text-decoration: none;

            padding: 13px 20px;

            border-radius: 6px;

            font-size: 15px;

            font-weight: bold;

        }


        .clear-button:hover {

            background: #4b5563;

        }


        /* ==========================================
           SEARCH RESULT TEXT
           ========================================== */

        .search-result {

            margin-bottom: 15px;

            color: #555;

            font-size: 14px;

        }


        .search-result strong {

            color: #222;

        }


        /* ==========================================
           TABLE
           ========================================== */

        table {

            width: 100%;

            min-width: 900px;

            border-collapse: collapse;

        }


        th {

            background: #2563eb;

            color: white;

            padding: 14px;

            text-align: left;

        }


        td {

            padding: 14px;

            border-bottom: 1px solid #ddd;

            color: #222;

        }


        tr:hover {

            background: #f5f7fb;

        }


        /* ==========================================
           USER TYPE
           ========================================== */

        .admin {

            display: inline-block;

            background: #dbeafe;

            color: #1d4ed8;

            padding: 5px 10px;

            border-radius: 5px;

            font-weight: bold;

            font-size: 13px;

        }


        .client {

            display: inline-block;

            background: #dcfce7;

            color: #166534;

            padding: 5px 10px;

            border-radius: 5px;

            font-weight: bold;

            font-size: 13px;

        }


        /* ==========================================
           EDIT BUTTON
           ========================================== */

        .edit-btn {

            display: inline-block;

            background: #2563eb;

            color: white;

            padding: 7px 12px;

            border-radius: 5px;

            text-decoration: none;

            font-size: 13px;

            margin-right: 5px;

        }


        .edit-btn:hover {

            background: #1d4ed8;

        }


        /* ==========================================
           DELETE BUTTON
           ========================================== */

        .delete-btn {

            display: inline-block;

            background: #dc2626;

            color: white;

            padding: 7px 12px;

            border-radius: 5px;

            text-decoration: none;

            font-size: 13px;

        }


        .delete-btn:hover {

            background: #b91c1c;

        }


        /* ==========================================
           EMPTY
           ========================================== */

        .empty {

            text-align: center;

            padding: 30px;

            color: #777;

        }


        /* ==========================================
           PHONE
           ========================================== */

        @media (max-width: 700px) {

            body {

                padding: 15px;

            }


            .header {

                padding: 20px;

            }


            .header h1 {

                font-size: 24px;

            }


            .container {

                padding: 15px;

            }


            .top-bar {

                flex-direction: column;

                align-items: flex-start;

            }


            .search-container {

                flex-direction: column;

            }


            .search-button,
            .clear-button {

                width: 100%;

                text-align: center;

            }

        }

    </style>

</head>


<body>


    <!-- ==========================================
         HEADER
         ========================================== -->

    <div class="header">

        <h1>
            BookShare
        </h1>

        <p>
            Book Donation & Redistribution Platform
        </p>

    </div>


    <!-- ==========================================
         MAIN CONTAINER
         ========================================== -->

    <div class="container">


        <!-- TOP BAR -->

        <div class="top-bar">

            <h2>
                Master List of Users
            </h2>


            <a
                href="index.php"
                class="back-button"
            >

                Back to Home

            </a>

        </div>


        <!-- ==========================================
             SEARCH FORM
             ========================================== -->

        <form
            action="master_list.php"
            method="GET"
            class="search-container"
        >

            <input
                type="text"
                name="search"
                class="search-input"
                placeholder="Search by ID, name, email, phone, organization, or user type..."
                value="<?php echo htmlspecialchars($search); ?>"
            >


            <button
                type="submit"
                class="search-button"
            >

                Search

            </button>


            <?php if ($search !== '') { ?>

                <a
                    href="master_list.php"
                    class="clear-button"
                >

                    Clear

                </a>

            <?php } ?>


        </form>


        <!-- SEARCH RESULT -->

        <?php if ($search !== '') { ?>

            <div class="search-result">

                Showing results for:

                <strong>
                    <?php echo htmlspecialchars($search); ?>
                </strong>

            </div>

        <?php } ?>


        <!-- ==========================================
             USER TABLE
             ========================================== -->

        <table>


            <tr>

                <th>
                    ID
                </th>

                <th>
                    Full Name
                </th>

                <th>
                    Email
                </th>

                <th>
                    Phone
                </th>

                <th>
                    School / Organization
                </th>

                <th>
                    User Type
                </th>

                <th>
                    Action
                </th>

            </tr>


            <?php

            if ($result->num_rows > 0) {

                while ($row = $result->fetch_assoc()) {

            ?>


                <tr>


                    <!-- ID -->

                    <td>

                        <?php

                        echo htmlspecialchars(
                            $row['id']
                        );

                        ?>

                    </td>


                    <!-- FULL NAME -->

                    <td>

                        <?php

                        echo htmlspecialchars(
                            $row['full_name']
                        );

                        ?>

                    </td>


                    <!-- EMAIL -->

                    <td>

                        <?php

                        echo htmlspecialchars(
                            $row['email']
                        );

                        ?>

                    </td>


                    <!-- PHONE -->

                    <td>

                        <?php

                        echo htmlspecialchars(
                            $row['phone']
                        );

                        ?>

                    </td>


                    <!-- ORGANIZATION -->

                    <td>

                        <?php

                        echo htmlspecialchars(
                            $row['organization']
                        );

                        ?>

                    </td>


                    <!-- USER TYPE -->

                    <td>

                        <?php

                        if (
                            $row['user_type']
                            === 'admin'
                        ) {

                        ?>

                            <span class="admin">

                                Admin

                            </span>

                        <?php

                        } else {

                        ?>

                            <span class="client">

                                Client User

                            </span>

                        <?php

                        }

                        ?>

                    </td>


                    <!-- ACTIONS -->

                    <td>

                        <a
                            href="edit_user.php?id=<?php echo $row['id']; ?>"
                            class="edit-btn"
                        >

                            Edit

                        </a>


                        <a
                            href="delete_user.php?id=<?php echo $row['id']; ?>"
                            class="delete-btn"
                        >

                            Delete

                        </a>

                    </td>


                </tr>


            <?php

                }

            } else {

            ?>


                <tr>

                    <td
                        colspan="7"
                        class="empty"
                    >

                        <?php

                        if ($search !== '') {

                            echo "No users found matching your search.";

                        } else {

                            echo "No users registered yet.";

                        }

                        ?>

                    </td>

                </tr>


            <?php

            }

            ?>


        </table>


    </div>


</body>

</html>


<?php

$conn->close();

?>