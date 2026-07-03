<style>
    .product-card {
        background: white;
        border-radius: 25px;
        overflow: hidden;
        box-shadow: var(--shadow-soft);
        transition: all 0.5s ease;
        height: 100%;
        position: relative;
    }
    .product-card:hover {
        transform: translateY(-15px);
        box-shadow: var(--shadow-hover);
    }
    .product-image {
        height: 220px;
        background: var(--green-mist);
        display: flex;
        align-items: center;
        justify-content: center;
        position: relative;
        overflow: hidden;
    }
    .product-img-cover {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .product-icon {
        font-size: 5rem;
        color: var(--green-primary);
        transition: all 0.4s ease;
    }
    .product-card:hover .product-icon {
        transform: scale(1.15) rotate(5deg);
    }
    .product-content {
        padding: 25px;
    }
    .product-category {
        color: var(--green-light);
        font-size: 0.85rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 1px;
        margin-bottom: 8px;
    }
    .product-title {
        font-size: 1.4rem;
        color: var(--green-dark);
        margin-bottom: 10px;
        transition: color 0.3s ease;
    }
    .product-card:hover .product-title {
        color: var(--green-primary);
    }
    .product-title a {
        color: inherit;
        text-decoration: none;
        transition: color 0.3s ease;
    }
    .product-title a:hover {
        color: var(--green-primary);
    }
    .product-footer {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding-top: 15px;
        border-top: 1px solid var(--green-mist);
    }
    .product-price {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--green-primary);
    }
    .product-price span {
        font-size: 0.9rem;
        color: var(--brown-light);
        font-weight: 400;
    }
    .btn-add-cart {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        border: none;
        background: linear-gradient(135deg, var(--green-primary), var(--green-light));
        color: white;
        font-size: 1.2rem;
        cursor: pointer;
        transition: all 0.3s ease;
        box-shadow: 0 4px 15px rgba(74, 124, 67, 0.3);
        display: flex;
        align-items: center;
        justify-content: center;
        text-decoration: none;
    }
    .btn-add-cart:hover {
        transform: scale(1.1) rotate(90deg);
        color: white;
    }
    .quick-view-btn {
        position: absolute;
        bottom: 20px;
        left: 50%;
        transform: translateX(-50%) translateY(20px);
        padding: 10px 25px;
        background: white;
        border: none;
        border-radius: 25px;
        color: var(--green-dark);
        font-weight: 600;
        cursor: pointer;
        box-shadow: var(--shadow-soft);
        opacity: 0;
        transition: all 0.3s ease;
        text-decoration: none;
        z-index: 2;
        white-space: nowrap;
    }
    .product-card:hover .quick-view-btn {
        opacity: 1;
        transform: translateX(-50%) translateY(0);
    }
    .quick-view-btn:hover {
        background: var(--green-primary);
        color: white;
    }
</style>
