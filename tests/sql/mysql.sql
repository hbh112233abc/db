CREATE TABLE authors (
    author_id INT AUTO_INCREMENT COMMENT '作者ID，唯一标识每位作者',
    first_name VARCHAR(100) COMMENT '名',
    last_name VARCHAR(100) COMMENT '姓',
    biography TEXT COMMENT '作者简介',
    PRIMARY KEY (author_id)
) COMMENT='存储作者信息的表';

CREATE TABLE categories (
    category_id INT AUTO_INCREMENT COMMENT '分类ID，唯一标识每个分类',
    category_name VARCHAR(100) COMMENT '分类名称',
    description TEXT COMMENT '分类描述',
    PRIMARY KEY (category_id)
) COMMENT='存储图书分类信息的表';

CREATE TABLE books (
    book_id INT AUTO_INCREMENT COMMENT '图书ID，唯一标识每本书',
    title VARCHAR(255) COMMENT '书名',
    isbn VARCHAR(20) UNIQUE COMMENT '国际标准书号，唯一',
    publisher VARCHAR(100) COMMENT '出版社',
    publication_date DATE COMMENT '出版日期',
    language VARCHAR(50) COMMENT '语言',
    page_count INT COMMENT '页数',
    summary TEXT COMMENT '摘要或简介',
    PRIMARY KEY (book_id)
) COMMENT='存储图书信息的表';

CREATE TABLE publishers (
    usci VARCHAR(20) NOT NULL COMMENT '组织架构码（统一社会信用代码）',
    name VARCHAR(100) NOT NULL COMMENT '出版社名称',
    address VARCHAR(255) COMMENT '出版社地址',
    city VARCHAR(50) COMMENT '城市',
    province VARCHAR(50) COMMENT '省份',
    country VARCHAR(50) COMMENT '国家',
    postal_code VARCHAR(20) COMMENT '邮政编码',
    phone VARCHAR(20) COMMENT '联系电话',
    email VARCHAR(100) COMMENT '电子邮箱',
    PRIMARY KEY (usci)
) COMMENT='存储出版社信息的表';

CREATE TABLE readers (
    reader_id INT AUTO_INCREMENT COMMENT '读者ID，唯一标识每位读者',
    first_name VARCHAR(100) COMMENT '名',
    last_name VARCHAR(100) COMMENT '姓',
    email VARCHAR(100) COMMENT '邮箱',
    registration_date DATE COMMENT '注册日期',
    PRIMARY KEY (reader_id)
) COMMENT='存储读者信息的表';

CREATE TABLE borrow_records (
    record_id INT AUTO_INCREMENT COMMENT '借阅记录ID，唯一标识每次借阅记录',
    reader_id INT COMMENT '外键，关联读者表',
    book_id INT COMMENT '外键，关联图书表',
    borrow_date DATE COMMENT '借阅日期',
    return_date DATE COMMENT '归还日期',
    status VARCHAR(50) COMMENT '借阅状态',
    PRIMARY KEY (record_id),
    FOREIGN KEY (reader_id) REFERENCES readers(reader_id),
    FOREIGN KEY (book_id) REFERENCES books(book_id)
) COMMENT='存储借阅记录的表';

CREATE TABLE book_authors (
    book_id INT COMMENT '外键，关联图书表',
    author_id INT COMMENT '外键，关联作者表',
    is_primary_author BOOLEAN COMMENT '标识是否为主作者',
    PRIMARY KEY (book_id, author_id),
    FOREIGN KEY (book_id) REFERENCES books(book_id),
    FOREIGN KEY (author_id) REFERENCES authors(author_id)
) COMMENT='存储图书与作者关联关系的表';

CREATE TABLE book_categories (
    book_id INT COMMENT '外键，关联图书表',
    category_id INT COMMENT '外键，关联图书分类表',
    PRIMARY KEY (book_id, category_id),
    FOREIGN KEY (book_id) REFERENCES books(book_id),
    FOREIGN KEY (category_id) REFERENCES categories(category_id)
) COMMENT='存储图书与分类关联关系的表';


-- 插入作者信息
INSERT INTO authors (first_name, last_name, biography) VALUES
('罗琳', 'J.K.', '英国作家，以《哈利·波特》系列最为人所知。'),
('乔治', '奥威尔', '英国小说家、散文家、记者和评论家，以20世纪中叶的作品闻名。'),
('简', '奥斯汀', '英国小说家，以她的六部主要小说而闻名。'),
('弗朗西斯·斯科特', '菲茨杰拉德', '美国小说家，其作品描绘了爵士时代。');

-- 插入图书分类信息
INSERT INTO categories (category_name, description) VALUES
('小说', '讲述虚构故事的书籍'),
('非小说', '基于真实事件或真实人物的书籍'),
('科幻小说', '想象其他世界或未来可能性的书籍'),
('经典', '被认为重要并成为我们文化传统一部分的书籍'),
('传记', '讲述某人生平的书籍');

-- 插入图书信息
INSERT INTO books (title, isbn, publisher, publication_date, language, page_count, summary) VALUES
('哈利·波特与魔法石', '9787108028874', '人民文学出版社', '1998-01-01', '中文', 223, '《哈利·波特》系列的第一本书。'),
('1984', '9787544274178', '上海译文出版社', '1950-01-01', '中文', 328, '一部反乌托邦社会科幻小说。'),
('傲慢与偏见', '9787544258609', '译林出版社', '1813-01-01', '中文', 432, '一部描绘礼仪的浪漫小说。'),
('了不起的盖茨比', '9787544274188', '上海译文出版社', '1925-01-01', '中文', 218, '一部以爵士时代为背景的小说。');

-- 插入出版社信息
INSERT INTO publishers (usci, name, address, city, province, country, postal_code, phone, email) VALUES
('123456789012345678', '中华书局', '北京市东城区', '北京', '北京', '中国', '100007', '010-12345678', 'service@zhongshu.com'),
('987654321098765432', '上海人民出版社', '上海市黄浦区', '上海', '上海', '中国', '200001', '021-87654321', 'contact@shanghaipeople.com'),
('564738219087654321', '江苏凤凰文艺出版社', '南京市玄武区', '南京', '江苏', '中国', '210008', '025-98765432', 'info@fenghuang.com'),
('112233445566778899', '浙江大学出版社', '杭州市西湖区', '杭州', '浙江', '中国', '310012', '0571-55667788', 'press@zju.edu.cn'),
('223344556677889900', '四川人民出版社', '成都市武侯区', '成都', '四川', '中国', '610000', '028-90123451', 'sichuanpub@sichuan.com');

-- 插入读者信息
INSERT INTO readers (first_name, last_name, email, registration_date) VALUES
('张', '伟', 'zhang.wei@example.com', '2023-01-15'),
('李', '娜', 'li.na@example.com', '2023-03-21'),
('王', '强', 'wang.qiang@example.com', '2023-05-09'),
('赵', '敏', 'zhao.min@example.com', '2023-07-23');

-- 插入借阅记录信息
INSERT INTO borrow_records (reader_id, book_id, borrow_date, return_date, status) VALUES
(1, 1, '2023-11-01', '2023-11-15', '已还'),
(2, 2, '2023-11-05', NULL, '借出'),
(3, 3, '2023-11-10', '2023-11-20', '已还'),
(4, 4, '2023-11-12', NULL, '借出');

-- 插入图书与作者关联信息
INSERT INTO book_authors (book_id, author_id, is_primary_author) VALUES
(1, 1, TRUE),
(2, 2, TRUE),
(3, 3, TRUE),
(4, 4, TRUE);

-- 插入图书与分类关联信息
INSERT INTO book_categories (book_id, category_id) VALUES
(1, 1),
(2, 1),
(3, 4),
(4, 1);

-- 插入出版社信息
INSERT INTO publishers (usci, name, address, city, province, country, postal_code, phone, email) VALUES
('123456789012345678', '中华书局', '北京市东城区', '北京', '北京', '中国', '100007', '010-12345678', 'service@zhongshu.com'),
('987654321098765432', '上海人民出版社', '上海市黄浦区', '上海', '上海', '中国', '200001', '021-87654321', 'contact@shanghaipeople.com'),
('564738219087654321', '江苏凤凰文艺出版社', '南京市玄武区', '南京', '江苏', '中国', '210008', '025-98765432', 'info@fenghuang.com'),
('112233445566778899', '浙江大学出版社', '杭州市西湖区', '杭州', '浙江', '中国', '310012', '0571-55667788', 'press@zju.edu.cn'),
('223344556677889900', '四川人民出版社', '成都市武侯区', '成都', '四川', '中国', '610000', '028-90123451', 'sichuanpub@sichuan.com');
