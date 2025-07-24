<?php
session_start();

if (!isset($_SESSION['name']) || !isset($_SESSION['email']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin page</title>
    <link rel="stylesheet" href="admin_page.css">
</head>
<body>
<aside class="sidebar" aria-label="Sidebar navigation">
    
    <div class="sidebar-header" aria-label="application name">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="12" cy="10" r="3.5" fill="#fff"/><path d="M12 2C7.03 2 3 6.03 3 11c0 5.25 7.19 10.61 8.13 11.29.23.16.51.16.74 0C13.81 21.61 21 16.25 21 11c0-4.97-4.03-9-9-9zm0 18.88C9.09 18.07 5 14.39 5 11c0-3.87 3.13-7 7-7s7 3.13 7 7c0 3.39-4.09 7.07-7 9.88z" fill="#d32f2f"/><circle cx="12" cy="10" r="2.5" fill="#d32f2f"/></svg>
      POS / INVENTORY
    </div>
    
    <nav>
      <a href="#" class="active" aria-current="page">Dashboard</a>
      <a href="#" aria-expanded="false">Products<span class="submenu-arrow">›</span></a> <!-- wip -->
      <a href="#">Inventory</a>
      <a href="#">Add Item</a>
      <a href="#">Sales Report</a>
      <a href="#">Requests</a>
    </nav>
    <div class="logout">
      <a href="#">Log out</a>
    </div>
  </aside>

  <main class="main-content" role="main">
    <div class="top-bar">
      <div class="breadcrumb" aria-label="You are here">
        <span class="breadcrumb-dot"></span>
        <span style="font-weight: bold; color: #222;">Dashboard</span>
      </div>

      <div class="search-container">
        <input type="search" aria-label="Search" placeholder="Search..." />
        <svg class="search-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
          <path d="M15.5 14h-.79l-.28-.27
                   A6.471 6.471 0 0016 9.5
                   6.5 6.5 0 109.5 16
                   c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5
                   4.99L20.49 19l-4.99-5zM9.5 14
                   A4.5 4.5 0 119.5 5a4.5 4.5 0 010 9z"/>
        </svg>
      </div>
    </div>

    <section class="card-container" aria-label="Sales and inventory summary cards">
      <article class="card sales" tabindex="0">Sales</article>
      <article class="card today-sales" tabindex="0">Today Total Sales</article>
      <article class="card total-category" tabindex="0">Total Category</article>
      <article class="card sales-return" tabindex="0">Sales Return</article>
    </section>
  </main>
</body>
</html>
